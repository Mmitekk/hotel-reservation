<?php

namespace Drupal\hotel_reservation\Mail;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Standalone SMTP transport for the Hotel Reservation module.
 *
 * Talks SMTP over a raw socket so mail delivery does not depend on the site
 * mail backend, hosting cron, or a relay on the same server. Supports implicit
 * TLS (SMTPS, e.g. port 465), STARTTLS (port 587) and plain connections, with
 * AUTH LOGIN / AUTH PLAIN. No external library is required.
 *
 * All errors are reported by throwing \RuntimeException with the SMTP server
 * reply, so callers can surface the real reason a message did not go out.
 */
class SmtpSender {

  /**
   * Configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Open socket resource.
   *
   * @var resource|null
   */
  protected $socket;

  /**
   * Conversation transcript (with credentials masked).
   *
   * @var string[]
   */
  protected $transcript = [];

  /**
   * Constructs an SmtpSender.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('hotel_reservation');
  }

  /**
   * Whether the module SMTP transport is switched on and configured.
   *
   * @return bool
   */
  public function isEnabled() {
    $config = $this->configFactory->get('hotel_reservation.settings');
    return (bool) $config->get('smtp_enabled') && trim((string) $config->get('smtp_host')) !== '';
  }

  /**
   * Sends a plain-text message through the configured SMTP server.
   *
   * @param string|string[] $to
   *   One or more recipient addresses.
   * @param string $subject
   *   The subject line (UTF-8, encoded automatically).
   * @param string $body
   *   The plain-text body (UTF-8, base64 encoded on the wire).
   * @param array $overrides
   *   Optional settings that override the stored configuration, used by the
   *   "send test email" button so a message can be tried before saving.
   *
   * @return bool
   *   TRUE when the server accepted the message.
   *
   * @throws \InvalidArgumentException
   *   When there is no recipient or no sender address.
   * @throws \RuntimeException
   *   When the connection, TLS handshake, authentication or delivery fails.
   */
  public function send($to, $subject, $body, array $overrides = []) {
    $settings = $this->resolveSettings($overrides);

    $recipients = $this->normalizeAddresses($to);
    if (empty($recipients)) {
      throw new \InvalidArgumentException('Не указан получатель письма.');
    }

    $from_email = $settings['smtp_from_email'] !== ''
      ? $settings['smtp_from_email']
      : (string) $this->configFactory->get('system.site')->get('mail');
    if ($from_email === '') {
      throw new \InvalidArgumentException('Не указан адрес отправителя (заполните «Email отправителя» или системный email сайта).');
    }
    if (!\Drupal::service('email.validator')->isValid($from_email)) {
      throw new \InvalidArgumentException('Некорректный адрес отправителя: ' . $from_email);
    }

    $this->transcript = [];
    $message = $this->buildMessage($from_email, $settings['smtp_from_name'], $recipients, $subject, $body);

    try {
      $this->connect($settings);
      $this->deliver($settings, $from_email, $recipients, $message);
      $this->disconnect();
    }
    catch (\Throwable $e) {
      $this->disconnect();
      if (!empty($settings['smtp_debug'])) {
        $this->logger->debug("SMTP conversation:\n@log", ['@log' => implode("\n", $this->transcript)]);
      }
      throw $e;
    }

    if (!empty($settings['smtp_debug'])) {
      $this->logger->debug("SMTP conversation:\n@log", ['@log' => implode("\n", $this->transcript)]);
    }

    return TRUE;
  }

  /**
   * Merges stored configuration with per-call overrides and defaults.
   *
   * @param array $overrides
   *   Keys may carry smtp_* configuration names.
   *
   * @return array
   *   Resolved settings.
   */
  protected function resolveSettings(array $overrides = []) {
    $config = $this->configFactory->get('hotel_reservation.settings');

    $settings = [
      'smtp_enabled' => (bool) $config->get('smtp_enabled'),
      'smtp_host' => trim((string) $config->get('smtp_host')),
      'smtp_port' => (int) $config->get('smtp_port') ?: 465,
      'smtp_encryption' => (string) ($config->get('smtp_encryption') ?: 'ssl'),
      'smtp_username' => (string) $config->get('smtp_username'),
      'smtp_password' => (string) $config->get('smtp_password'),
      'smtp_from_email' => trim((string) $config->get('smtp_from_email')),
      'smtp_from_name' => (string) $config->get('smtp_from_name'),
      'smtp_timeout' => (int) $config->get('smtp_timeout') ?: 15,
      'smtp_debug' => (bool) $config->get('smtp_debug'),
    ];

    foreach ($overrides as $key => $value) {
      if (array_key_exists($key, $settings)) {
        $settings[$key] = $value;
      }
    }

    $settings['smtp_host'] = trim((string) $settings['smtp_host']);
    $settings['smtp_encryption'] = in_array($settings['smtp_encryption'], ['none', 'tls', 'ssl'], TRUE)
      ? $settings['smtp_encryption']
      : 'ssl';

    if ($settings['smtp_host'] === '') {
      throw new \InvalidArgumentException('Не указан SMTP-хост.');
    }

    return $settings;
  }

  /**
   * Normalizes recipients into a clean, unique list of addresses.
   *
   * @param string|string[] $to
   *   Addresses, possibly comma separated.
   *
   * @return string[]
   *   Validated addresses.
   */
  protected function normalizeAddresses($to) {
    $raw = is_array($to) ? $to : preg_split('/[,;]+/', (string) $to);
    $validator = \Drupal::service('email.validator');
    $clean = [];
    foreach ((array) $raw as $address) {
      $address = trim((string) $address);
      if ($address === '') {
        continue;
      }
      if (!$validator->isValid($address)) {
        throw new \InvalidArgumentException('Некорректный адрес получателя: ' . $address);
      }
      $clean[strtolower($address)] = $address;
    }
    return array_values($clean);
  }

  /**
   * Opens the socket and brings the session to an authenticated state.
   *
   * @param array $settings
   *   Resolved settings.
   */
  protected function connect(array $settings) {
    $host = $settings['smtp_host'];
    $port = (int) $settings['smtp_port'];
    $timeout = max(1, (int) $settings['smtp_timeout']);
    $transport = $settings['smtp_encryption'] === 'ssl' ? 'ssl://' : 'tcp://';

    $context = stream_context_create([
      'ssl' => [
        'verify_peer' => TRUE,
        'verify_peer_name' => TRUE,
        'allow_self_signed' => FALSE,
      ],
    ]);

    if (filter_var($host, FILTER_VALIDATE_IP) === FALSE && gethostbyname($host) === $host) {
      throw new \RuntimeException(sprintf('Не удалось разрешить имя %s: на сервере не работает DNS. Проверьте /etc/resolv.conf или укажите IP-адрес SMTP-сервера.', $host));
    }

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client(
      $transport . $host . ':' . $port,
      $errno,
      $errstr,
      $timeout,
      STREAM_CLIENT_CONNECT,
      $context
    );
    if (!$socket) {
      throw new \RuntimeException(sprintf('Не удалось подключиться к SMTP %s:%d — %s (%d).', $host, $port, $errstr ?: 'нет ответа', $errno));
    }

    $this->socket = $socket;
    stream_set_timeout($socket, $timeout);

    $this->expect($this->read(), [220], 'приветствие сервера');
    $this->command('EHLO ' . $this->clientName(), [250], 'EHLO');

    if ($settings['smtp_encryption'] === 'tls') {
      $this->command('STARTTLS', [220], 'STARTTLS');
      $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
      foreach (['STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT', 'STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT'] as $constant) {
        if (defined($constant)) {
          $crypto |= constant($constant);
        }
      }
      if (!@stream_socket_enable_crypto($socket, TRUE, $crypto)) {
        throw new \RuntimeException('Не удалось включить TLS (STARTTLS). Проверьте шифрование и порт.');
      }
      $this->command('EHLO ' . $this->clientName(), [250], 'EHLO после STARTTLS');
    }

    if ((string) $settings['smtp_username'] !== '') {
      $this->authenticate((string) $settings['smtp_username'], (string) $settings['smtp_password']);
    }
  }

  /**
   * Performs AUTH LOGIN, falling back to AUTH PLAIN.
   *
   * @param string $username
   *   SMTP login.
   * @param string $password
   *   SMTP password or app password.
   */
  protected function authenticate($username, $password) {
    $this->write('AUTH LOGIN', TRUE);
    $response = $this->read();
    $code = (int) substr(ltrim($response), 0, 3);

    if ($code !== 334) {
      $token = base64_encode("\0" . $username . "\0" . $password);
      $this->write('AUTH PLAIN ' . $token, TRUE);
      $this->expect($this->read(), [235], 'AUTH PLAIN');
      return;
    }

    $this->write(base64_encode($username), TRUE);
    $this->expect($this->read(), [334], 'AUTH логин');
    $this->write(base64_encode($password), TRUE);
    $this->expect($this->read(), [235], 'AUTH пароль');
  }

  /**
   * Runs the MAIL FROM / RCPT TO / DATA exchange.
   *
   * @param array $settings
   *   Resolved settings.
   * @param string $from_email
   *   Envelope sender.
   * @param string[] $recipients
   *   Envelope recipients.
   * @param string $message
   *   Full message with headers.
   */
  protected function deliver(array $settings, $from_email, array $recipients, $message) {
    $this->command('MAIL FROM:<' . $from_email . '>', [250], 'MAIL FROM');
    foreach ($recipients as $recipient) {
      $this->command('RCPT TO:<' . $recipient . '>', [250, 251], 'RCPT TO ' . $recipient);
    }
    $this->command('DATA', [354], 'DATA');
    $this->write($message . "\r\n.", TRUE);
    $this->expect($this->read(), [250], 'приём письма');
    $this->write('QUIT', TRUE);
  }

  /**
   * Closes the socket if it is still open.
   */
  protected function disconnect() {
    if (is_resource($this->socket)) {
      @fclose($this->socket);
    }
    $this->socket = NULL;
  }

  /**
   * Sends a command and asserts the reply code.
   *
   * @param string $command
   *   The SMTP command.
   * @param int[] $expected
   *   Acceptable reply codes.
   * @param string $label
   *   Human readable step name.
   *
   * @return string
   *   The raw reply.
   */
  protected function command($command, array $expected, $label) {
    $this->write($command);
    $response = $this->read();
    $this->expect($response, $expected, $label);
    return $response;
  }

  /**
   * Asserts an SMTP reply code.
   *
   * @param string $response
   *   Raw reply.
   * @param int[] $expected
   *   Acceptable codes.
   * @param string $label
   *   Step name used in the error message.
   */
  protected function expect($response, array $expected, $label) {
    $code = (int) substr(ltrim($response), 0, 3);
    if (!in_array($code, $expected, TRUE)) {
      throw new \RuntimeException(sprintf('SMTP: шаг «%s» вернул %d, ожидалось %s. Ответ сервера: %s', $label, $code, implode('/', $expected), trim($response)));
    }
  }

  /**
   * Reads a (possibly multi-line) SMTP reply.
   *
   * @return string
   *   The reply.
   */
  protected function read() {
    if (!is_resource($this->socket)) {
      throw new \RuntimeException('SMTP-соединение закрыто.');
    }
    $reply = '';
    while (($line = fgets($this->socket, 515)) !== FALSE) {
      $reply .= $line;
      if (strlen($line) < 4 || $line[3] === ' ') {
        break;
      }
    }
    if ($reply === '') {
      $meta = stream_get_meta_data($this->socket);
      throw new \RuntimeException(!empty($meta['timed_out'])
        ? 'SMTP: таймаут ожидания ответа сервера.'
        : 'SMTP-сервер закрыл соединение без ответа.');
    }
    $this->transcript[] = '< ' . rtrim($reply);
    return $reply;
  }

  /**
   * Writes raw data to the socket.
   *
   * @param string $data
   *   Payload without the trailing CRLF.
   * @param bool $sensitive
   *   TRUE to mask the payload in the transcript (credentials, message body).
   */
  protected function write($data, $sensitive = FALSE) {
    if (!is_resource($this->socket)) {
      throw new \RuntimeException('SMTP-соединение закрыто.');
    }
    $this->transcript[] = '> ' . ($sensitive ? '[данные скрыты]' : $data);
    if (@fwrite($this->socket, $data . "\r\n") === FALSE) {
      throw new \RuntimeException('SMTP: не удалось передать данные на сервер.');
    }
  }

  /**
   * Returns the name used in EHLO.
   *
   * @return string
   *   A host name.
   */
  protected function clientName() {
    $site_mail = (string) $this->configFactory->get('system.site')->get('mail');
    $host = $site_mail !== '' ? (string) parse_url('mailto:' . $site_mail, PHP_URL_HOST) : '';
    if ($host === '') {
      $host = (string) parse_url('http://' . $site_mail, PHP_URL_HOST);
    }
    if ($host === '') {
      $host = gethostname() ?: 'localhost';
    }
    return $host;
  }

  /**
   * Builds the RFC 5322 message with a base64 encoded UTF-8 body.
   *
   * @param string $from_email
   *   Sender address.
   * @param string $from_name
   *   Sender display name, may be empty.
   * @param string[] $recipients
   *   Recipient addresses.
   * @param string $subject
   *   Subject.
   * @param string $body
   *   Plain-text body.
   *
   * @return string
   *   The full message.
   */
  protected function buildMessage($from_email, $from_name, array $recipients, $subject, $body) {
    $eol = "\r\n";

    $headers = [];
    $headers[] = 'Date: ' . date(DATE_RFC2822);
    $headers[] = 'From: ' . $this->formatAddress($from_email, $from_name);
    $headers[] = 'To: ' . implode(', ', array_map(function ($address) {
      return $this->formatAddress($address, '');
    }, $recipients));
    $headers[] = 'Subject: ' . $this->encodeHeader($subject);
    $headers[] = 'Message-ID: ' . $this->messageId($from_email);
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: base64';
    $headers[] = 'X-Mailer: Hotel Reservation (independent SMTP)';

    $normalized = str_replace(["\r\n", "\r"], "\n", (string) $body);
    $encoded_body = rtrim(chunk_split(base64_encode($normalized), 76, $eol));

    return implode($eol, $headers) . $eol . $eol . $encoded_body;
  }

  /**
   * Formats a single address, encoding a non-ASCII display name.
   *
   * @param string $email
   *   The address.
   * @param string $name
   *   Display name, may be empty.
   *
   * @return string
   *   A header-ready address.
   */
  protected function formatAddress($email, $name) {
    $name = trim((string) $name);
    if ($name === '') {
      return $email;
    }
    if (!preg_match('/[^\x20-\x7e]/', $name)) {
      $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], preg_replace('/[\r\n]+/', ' ', $name));
      return '"' . $escaped . '" <' . $email . '>';
    }
    return $this->encodeHeader($name) . ' <' . $email . '>';
  }

  /**
   * Encodes a header value when it carries non-ASCII characters.
   *
   * Implements RFC 2047 encoded-words directly, so the module does not
   * depend on the mbstring extension being enabled on the server.
   *
   * @param string $value
   *   Raw header value.
   *
   * @return string
   *   Encoded value.
   */
  protected function encodeHeader($value) {
    $value = trim((string) $value);
    if ($value === '') {
      return '(без темы)';
    }
    $value = preg_replace('/[\r\n]+/', ' ', $value);

    if (!preg_match('/[^\x20-\x7e]/', $value)) {
      return $value;
    }

    // Split on character boundaries, never in the middle of a UTF-8 byte
    // sequence, keeping each encoded-word under the 75 character limit.
    if (preg_match('//u', $value)) {
      $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
    }
    else {
      $characters = str_split($value);
    }

    $words = [];
    $chunk = '';
    foreach ($characters as $character) {
      if ($chunk !== '' && strlen($chunk . $character) > 45) {
        $words[] = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
        $chunk = '';
      }
      $chunk .= $character;
    }
    if ($chunk !== '') {
      $words[] = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
    }

    return implode(' ', $words);
  }

  /**
   * Builds a unique Message-ID header value.
   *
   * @param string $from_email
   *   Sender address.
   *
   * @return string
   *   The Message-ID.
   */
  protected function messageId($from_email) {
    $domain = (string) substr(strrchr($from_email, '@'), 1);
    if ($domain === '') {
      $domain = $this->clientName();
    }
    try {
      $entropy = bin2hex(random_bytes(8));
    }
    catch (\Throwable $e) {
      $entropy = uniqid('', TRUE);
    }
    return '<' . $entropy . '.' . time() . '@' . $domain . '>';
  }

}
