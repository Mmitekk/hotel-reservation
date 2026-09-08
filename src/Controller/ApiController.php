<?php

namespace Drupal\hotel_reservation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * API controller for Hotel Reservation frontend endpoints.
 */
class ApiController extends ControllerBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * Checks room availability and returns available rooms with prices.
   *
   * Accepts JSON POST data: check_in, check_out, capacity (desired room
   * capacity, exact match; falls back to guest_count for BC).
   * If no rooms with the requested capacity are free, other available
   * rooms are offered with exact_match = FALSE and a notice message.
   * Returns JSON: [{id, name, capacity, base_price, total_price, nights, available}].
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with available rooms.
   */
  public function checkAvailability(Request $request) {
    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    if (empty($data)) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Неверные данные JSON.',
      ], 400);
    }

    $check_in = $data['check_in'] ?? '';
    $check_out = $data['check_out'] ?? '';
    $guest_count = (int) ($data['guest_count'] ?? 1);
    // Desired capacity: exact room category filter, decoupled from guests.
    $wanted_capacity = isset($data['capacity']) ? (int) $data['capacity'] : $guest_count;

    // Validate dates.
    if (empty($check_in) || empty($check_out)) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Укажите check_in и check_out.',
      ], 400);
    }

    if (!$this->validateDate($check_in) || !$this->validateDate($check_out)) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Неверный формат даты. Используйте Г-М-Д.',
      ], 400);
    }

    if ($check_out <= $check_in) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Дата выезда должна быть позже даты заезда.',
      ], 400);
    }

    if ($guest_count < 1) {
      $guest_count = 1;
    }
    if ($wanted_capacity < 1) {
      $wanted_capacity = 1;
    }

    // Validate against config min/max stay.
    $config = $this->config('hotel_reservation.settings');
    $min_stay = (int) $config->get('min_stay_nights') ?: 1;
    $max_stay = (int) $config->get('max_stay_nights') ?: 30;
    $nights = (new \DateTime($check_out))->diff(new \DateTime($check_in))->days;

    if ($nights < $min_stay) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Минимальное количество ночей: ' . $min_stay . '.',
        'min_stay' => $min_stay,
      ], 400);
    }

    if ($nights > $max_stay) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Максимальное количество ночей: ' . $max_stay . '.',
        'max_stay' => $max_stay,
      ], 400);
    }

    // Get rooms with the exact requested capacity.
    $exact_rooms = hotel_reservation_get_available_rooms($check_in, $check_out, $wanted_capacity, TRUE);
    $exact_match = !empty($exact_rooms);

    if ($exact_match) {
      $rooms_to_show = $exact_rooms;
      $notice = '';
    }
    else {
      // No rooms with this capacity — offer all other available rooms.
      $rooms_to_show = hotel_reservation_get_available_rooms($check_in, $check_out);
      $notice = !empty($rooms_to_show)
        ? (string) $this->t('У нас сейчас нет свободных номеров вместимостью @n. Посмотрите другие варианты:', ['@n' => $wanted_capacity])
        : '';
    }

    $results = [];
    foreach ($rooms_to_show as $room) {
      $pricing = hotel_reservation_calculate_price($room->id(), $check_in, $check_out);
      $results[] = $this->buildRoomResult($room, $pricing);
    }

    return new SymfonyJsonResponse([
      'success' => TRUE,
      'rooms' => $results,
      'check_in' => $check_in,
      'check_out' => $check_out,
      'nights' => $nights,
      'guest_count' => $guest_count,
      'requested_capacity' => $wanted_capacity,
      'exact_match' => $exact_match,
      'notice' => $notice,
    ]);
  }

  /**
   * Builds a single room array for the availability response.
   *
   * @param \Drupal\hotel_reservation\Entity\Room $room
   *   The room entity.
   * @param array $pricing
   *   Price data from hotel_reservation_calculate_price().
   *
   * @return array
   *   Room data array.
   */
  protected function buildRoomResult($room, array $pricing): array {
    $rawDesc = $room->getDescription() ?: '';
    $plainDesc = trim(strip_tags(html_entity_decode($rawDesc, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $plainDesc = html_entity_decode($plainDesc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plainDesc = preg_replace('/\s+/', ' ', $plainDesc);
    $imageUrl = NULL;
    $imageAlt = '';
    if (method_exists($room, 'getImageUrl')) {
      $imageUrl = $room->getImageUrl();
      $imageAlt = $room->getImageAlt();
    }
    $roomTypeId = $room->get('room_type')->value ?? 'standard';
    $typeLabel = $roomTypeId;
    $typeColor = '#6b7280';
    try {
      $typeEntity = $this->entityTypeManager->getStorage('hr_room_type')->load($roomTypeId);
      if ($typeEntity) {
        $typeLabel = $typeEntity->label();
        $typeColor = $typeEntity->getColor();
      }
    }
    catch (\Throwable $e) {
    }
    return [
      'id' => (int) $room->id(),
      'name' => $room->label(),
      'room_type' => $roomTypeId,
      'room_type_label' => $typeLabel,
      'type_color' => $typeColor,
      'teaser' => method_exists($room, 'getTeaserPlain') ? $room->getTeaserPlain(200) : $plainDesc,
      'description' => $plainDesc,
      'image_url' => $imageUrl,
      'image_alt' => $imageAlt,
      'slides' => method_exists($room, 'getSliderImages') ? $room->getSliderImages() : [],
      'capacity' => $room->getCapacity(),
      'base_price' => number_format((float) $room->getBasePrice(), 2, '.', ''),
      'total_price' => number_format($pricing['total'], 2, '.', ''),
      'nights' => $pricing['nights'],
      'available' => TRUE,
      'amenities' => $room->getAmenities(),
    ];
  }

  /**
   * Submits a new reservation from the frontend booking form.
   *
   * Accepts JSON POST data: room_id, check_in, check_out, guest_name,
   * guest_phone, guest_email, guest_count, notes.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with success/error.
   */
  public function submitReservation(Request $request) {
    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    if (empty($data)) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Неверные данные JSON.',
      ], 400);
    }

    $room_id = (int) ($data['room_id'] ?? 0);
    $check_in = $data['check_in'] ?? '';
    $check_out = $data['check_out'] ?? '';
    $guest_name = trim($data['guest_name'] ?? '');
    $guest_phone = trim($data['guest_phone'] ?? '');
    $guest_email = trim($data['guest_email'] ?? '');
    $guest_count = (int) ($data['guest_count'] ?? 1);
    $notes = trim($data['notes'] ?? '');

    // Validate required fields.
    $errors = [];

    if (empty($room_id)) {
      $errors[] = 'Укажите room_id.';
    }
    if (empty($check_in) || !$this->validateDate($check_in)) {
      $errors[] = 'Укажите корректную дату заезда (Г-М-Д).';
    }
    if (empty($check_out) || !$this->validateDate($check_out)) {
      $errors[] = 'Укажите корректную дату выезда (Г-М-Д).';
    }
    if ($check_out <= $check_in) {
      $errors[] = 'Дата выезда должна быть позже даты заезда.';
    }
    if (empty($guest_name)) {
      $errors[] = 'Укажите имя гостя.';
    }
    if (empty($guest_phone)) {
      $errors[] = 'Укажите телефон гостя.';
    }
    if (!empty($guest_email) && !\Drupal::service('email.validator')->isValid($guest_email)) {
      $errors[] = 'Некорректный email.';
    }
    if ($guest_count < 1) {
      $errors[] = 'Количество гостей должно быть не менее 1.';
    }

    // Validate against config min/max stay.
    $config = $this->config('hotel_reservation.settings');
    $min_stay = (int) $config->get('min_stay_nights') ?: 1;
    $max_stay = (int) $config->get('max_stay_nights') ?: 30;
    $nights = (new \DateTime($check_out))->diff(new \DateTime($check_in))->days;

    if ($nights < $min_stay) {
      $errors[] = 'Минимальное количество ночей: ' . $min_stay . '.';
    }
    if ($nights > $max_stay) {
      $errors[] = 'Максимальное количество ночей: ' . $max_stay . '.';
    }

    if (!empty($errors)) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'errors' => $errors,
      ], 400);
    }

    // Validate room exists and is published.
    $room = $this->entityTypeManager->getStorage('hr_room')->load($room_id);
    if (!$room) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Номер не найден.',
      ], 404);
    }
    if (!$room->isPublished()) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Номер недоступен для бронирования.',
      ], 400);
    }

    // Check availability by dates only: room capacity is a category filter
    // and is not linked to the number of guests.
    $available_rooms = hotel_reservation_get_available_rooms($check_in, $check_out);
    if (!isset($available_rooms[$room_id])) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Номер занят на выбранные даты.',
      ], 409);
    }

    // Calculate price.
    $pricing = hotel_reservation_calculate_price($room_id, $check_in, $check_out);
    $total_price = number_format($pricing['total'], 2, '.', '');

    // Auto-create (or link) the guest account: by email, or by phone
    // digits as login when email is missing. Never breaks the booking.
    $guest_uid = NULL;
    $account_created = FALSE;
    $login_url = '';
    $guest_account = [];
    if ($config->get('auto_create_guest_account') ?? TRUE) {
      $guest_account = $this->ensureGuestAccount($guest_name, $guest_email, $guest_phone);
      if (!empty($guest_account['account'])) {
        $guest_uid = (int) $guest_account['account']->id();
        $account_created = !empty($guest_account['created']);
        $login_url = (string) ($guest_account['login_url'] ?? '');
      }
    }

    // Create the reservation entity.
    try {
      $reservation_values = [
        'room_id' => ['target_id' => $room_id],
        'check_in' => $check_in,
        'check_out' => $check_out,
        'guest_name' => $guest_name,
        'guest_phone' => $guest_phone,
        'guest_email' => $guest_email,
        'guest_count' => $guest_count,
        'total_price' => $total_price,
        'notes' => ['value' => $notes, 'format' => 'plain_text'],
        'status' => 'pending',
      ];
      if ($guest_uid !== NULL && hotel_reservation_reservation_has_uid()) {
        $reservation_values['uid'] = ['target_id' => $guest_uid];
      }
      $reservation = $this->entityTypeManager->getStorage('hr_reservation')->create($reservation_values);
      $reservation->save();
    }
    catch (\Throwable $e) {
      $this->getLogger('hotel_reservation')->error('Failed to create reservation: @message | File: @file Line: @line', [
        '@message' => $e->getMessage(),
        '@file' => $e->getFile(),
        '@line' => $e->getLine(),
      ]);
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Не удалось создать бронирование. Попробуйте ещё раз.',
      ], 500);
    }

    // Send confirmation email.
    $hotel_name = $config->get('hotel_name') ?: $this->config('system.site')->get('name');
    $currency = $config->get('currency_symbol') ?: '₽';
    $langcode = $this->languageManager()->getCurrentLanguage()->getId();

    // Shared mail tokens.
    $mail_tokens = [
      'guest' => $guest_name,
      'email' => $guest_email ?: '—',
      'phone' => $guest_phone,
      'room' => $room->label(),
      'check_in' => (new \DateTime($check_in))->format('d.m.Y'),
      'check_out' => (new \DateTime($check_out))->format('d.m.Y'),
      'count' => $guest_count,
      'total' => $total_price,
      'currency' => $currency,
      'notes' => $notes ?: 'Нет',
      'hotel' => $hotel_name,
      'login_url' => $login_url,
    ];

    // Send admin notification.
    if ((bool) $config->get('enable_admin_notification')) {
      $admin_email = $config->get('admin_notification_email');
      if (!empty($admin_email)) {
        $params['guest_name'] = $guest_name;
        $params['message'] = hotel_reservation_build_mail_text(
          hotel_reservation_get_mail_text('admin_new_booking'),
          $mail_tokens
        );

        try {
          \Drupal::service('plugin.manager.mail')->mail(
            'hotel_reservation',
            'reservation_admin_notification',
            $admin_email,
            $langcode,
            $params
          );
        }
        catch (\Throwable $e) {
          // A broken mail backend must not fail the booking or corrupt
          // the JSON response (e.g. PHP warnings printed into output).
          $this->getLogger('hotel_reservation')->warning('Admin notification email failed for reservation @id: @message', [
            '@id' => $reservation->id(),
            '@message' => $e->getMessage(),
          ]);
        }
      }
    }

    // Auto-confirm if configured and guest email provided.
    if ((bool) $config->get('enable_guest_confirmation') && !empty($guest_email)) {
      // Send a pending notification (not yet confirmed).
      $pending_template = hotel_reservation_get_mail_text('guest_pending');
      if ($login_url !== '' && strpos($pending_template, '@login_url') === FALSE) {
        $pending_template .= "\n\n" . (string) $this->t('Личный кабинет и ваши бронирования: @login_url', ['@login_url' => $login_url]);
      }
      $params2['message'] = hotel_reservation_build_mail_text(
        $pending_template,
        $mail_tokens
      );

      try {
        \Drupal::service('plugin.manager.mail')->mail(
          'hotel_reservation',
          'reservation_confirmation',
          $guest_email,
          $langcode,
          $params2
        );
      }
      catch (\Throwable $e) {
        // See above: mail failures must not break the booking response.
        $this->getLogger('hotel_reservation')->warning('Guest confirmation email failed for reservation @id: @message', [
          '@id' => $reservation->id(),
          '@message' => $e->getMessage(),
        ]);
      }
    }

    // One-time token so a freshly created account owner can set a password
    // right away. Issued only for newly created accounts, never for linked
    // existing ones. Single use, 24h expiry.
    $account_token = '';
    if ($account_created && $guest_uid !== NULL) {
      try {
        $raw_token = bin2hex(random_bytes(32));
        \Drupal::state()->set('hotel_reservation.pw.' . $reservation->id(), [
          'uid' => $guest_uid,
          'hash' => hash('sha256', $raw_token),
          'expires' => \Drupal::time()->getRequestTime() + 86400,
        ]);
        $account_token = $raw_token;
      }
      catch (\Throwable $e) {
        $this->getLogger('hotel_reservation')->error('Failed to issue password token: @message', [
          '@message' => $e->getMessage(),
        ]);
      }
    }

    // Log the guest into a freshly created account right away so the
    // frontend can take them straight to their bookings. Only for accounts
    // created by this request and only for anonymous visitors: an already
    // authenticated user (e.g. an admin testing the form) must never be
    // logged out of their own account.
    $logged_in = FALSE;
    if ($account_created && $guest_uid !== NULL && $this->currentUser()->isAnonymous() && !empty($guest_account['account'])) {
      try {
        user_login_finalize($guest_account['account']);
        $logged_in = TRUE;
      }
      catch (\Throwable $e) {
        $this->getLogger('hotel_reservation')->warning('Auto-login after booking failed for uid @uid: @message', [
          '@uid' => $guest_uid,
          '@message' => $e->getMessage(),
        ]);
      }
    }

    $this->getLogger('hotel_reservation')->info('Booking @rid submitted: guest account uid=@uid, created=@created, password token=@token, auto-login=@login.', [
      '@rid' => $reservation->id(),
      '@uid' => $guest_uid !== NULL ? $guest_uid : 'none',
      '@created' => $account_created ? 'yes' : 'no',
      '@token' => $account_token !== '' ? 'issued' : 'none',
      '@login' => $logged_in ? 'yes' : 'no',
    ]);

    return new SymfonyJsonResponse([
      'success' => TRUE,
      'message' => $this->t('Бронирование создано. Ваша заявка ожидает подтверждения.'),
      'reservation_id' => (int) $reservation->id(),
      'account_created' => $account_created,
      'account_token' => $account_token,
      // An existing account was linked (not created): no password token is
      // issued, the guest logs in with their own credentials.
      'account_linked' => !$account_created && $guest_uid !== NULL,
      'logged_in' => $logged_in,
      'redirect' => $logged_in ? '/hotel-reservation/my-bookings' : '',
    ]);
  }

  /**
   * Validates a one-time password token.
   *
   * @param int $reservation_id
   *   The reservation ID from the request.
   * @param string $token
   *   The raw token from the request.
   *
   * @return array
   *   Either ['account' => $account, 'reservation' => $reservation] or
   *   ['error' => $message, 'status' => $code].
   */
  protected function loadPasswordTokenContext(int $reservation_id, string $token): array {
    $expired = [
      'error' => 'Ссылка устарела. Войдите через страницу входа или восстановление пароля.',
      'status' => 403,
    ];
    if (empty($reservation_id) || $token === '') {
      return ['error' => 'Некорректные данные.', 'status' => 400];
    }

    $state_key = 'hotel_reservation.pw.' . $reservation_id;
    try {
      $stored = \Drupal::state()->get($state_key);
    }
    catch (\Throwable $e) {
      $stored = NULL;
    }
    if (empty($stored) || empty($stored['hash']) || empty($stored['uid'])) {
      return $expired;
    }
    if ($stored['expires'] < \Drupal::time()->getRequestTime() || !hash_equals($stored['hash'], hash('sha256', $token))) {
      return $expired;
    }

    $account = $this->entityTypeManager->getStorage('user')->load((int) $stored['uid']);
    if (!$account) {
      return ['error' => 'Учётная запись не найдена.', 'status' => 404];
    }
    // The token must belong to the account linked to this reservation.
    $reservation = $this->entityTypeManager->getStorage('hr_reservation')->load($reservation_id);
    $linked_uid = NULL;
    if ($reservation && hotel_reservation_reservation_has_uid()) {
      $linked_uid = $reservation->get('uid')->target_id;
    }
    elseif ($reservation) {
      // Fallback when update 10009 has not run: match by contact data.
      $reservation_email = trim((string) $reservation->get('guest_email')->value);
      if ($reservation_email !== '' && $reservation_email === $account->getEmail()) {
        $linked_uid = (int) $account->id();
      }
      else {
        $reservation_digits = preg_replace('/\D/', '', (string) $reservation->get('guest_phone')->value);
        if (strlen($reservation_digits) === 11 && $reservation_digits[0] === '8') {
          $reservation_digits = '7' . substr($reservation_digits, 1);
        }
        $account_digits = preg_replace('/\D/', '', $account->getAccountName());
        if (strlen($account_digits) === 11 && $account_digits[0] === '8') {
          $account_digits = '7' . substr($account_digits, 1);
        }
        if ($reservation_digits !== '' && $reservation_digits === $account_digits) {
          $linked_uid = (int) $account->id();
        }
      }
    }
    if ($linked_uid === NULL || (int) $linked_uid !== (int) $account->id()) {
      return $expired;
    }

    return ['account' => $account, 'reservation' => $reservation];
  }

  /**
   * Password setup page for a freshly created guest account.
   *
   * Accepts ?reservation_id= and ?token= query parameters (the one-time
   * token from the submitReservation response). Renders a password form;
   * the form itself posts to the set-password JSON endpoint.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array.
   */
  public function setPasswordPage(Request $request) {
    $reservation_id = (int) $request->query->get('reservation_id');
    $token = (string) $request->query->get('token', '');
    $context = $this->loadPasswordTokenContext($reservation_id, $token);

    if (isset($context['error'])) {
      return [
        '#markup' => '<div class="hr-set-password"><p>' . $this->t('Ссылка недействительна или устарела. Войдите через страницу входа или воспользуйтесь восстановлением пароля.') . '</p></div>',
        '#allowed_tags' => ['div', 'p'],
        '#cache' => ['max-age' => 0],
      ];
    }

    return [
      '#markup' => '<div class="hr-set-password" data-reservation-id="' . $reservation_id . '" data-token="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '" data-api-url="' . Url::fromRoute('hotel_reservation.api_set_guest_password')->toString() . '">'
        . '<h2>' . $this->t('Установка постоянного пароля') . '</h2>'
        . '<p>' . $this->t('Задайте постоянный пароль для входа в личный кабинет, где видны ваши бронирования.') . '</p>'
        . '<div class="hr-success-password__errors"></div>'
        . '<input type="password" class="hr-success-password__input" autocomplete="new-password" placeholder="' . $this->t('Пароль (минимум 8 символов)') . '">'
        . '<input type="password" class="hr-success-password__confirm" autocomplete="new-password" placeholder="' . $this->t('Повторите пароль') . '">'
        . '<button type="button" class="hr-btn hr-btn--primary hr-set-password__btn">' . $this->t('Сохранить пароль и войти') . '</button>'
        . '</div>',
      '#allowed_tags' => ['div', 'h2', 'p', 'input', 'button'],
      '#attached' => [
        'library' => [
          'hotel_reservation/set-password',
        ],
        'html_head' => [
          [
            [
              '#tag' => 'meta',
              '#attributes' => ['name' => 'robots', 'content' => 'noindex, nofollow'],
            ],
            'hr-set-password-noindex',
          ],
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Sets the password of a freshly created guest account.
   *
   * Accepts JSON POST data: reservation_id, token, password.
   * The token is issued once in submitReservation response and is valid
   * for 24 hours. On success the guest is logged in.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with success/error.
   */
  public function setGuestPassword(Request $request) {
    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    $reservation_id = (int) ($data['reservation_id'] ?? 0);
    $token = (string) ($data['token'] ?? '');
    $password = (string) ($data['password'] ?? '');

    if (empty($reservation_id) || $token === '') {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Некорректные данные.',
      ], 400);
    }
    if (mb_strlen($password) < 8) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Пароль должен содержать не менее 8 символов.',
      ], 400);
    }

    $context = $this->loadPasswordTokenContext($reservation_id, $token);
    if (isset($context['error'])) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => $context['error'],
      ], $context['status']);
    }
    $account = $context['account'];

    try {
      $account->setPassword($password);
      $account->save();
      \Drupal::state()->delete('hotel_reservation.pw.' . $reservation_id);

      try {
        user_login_finalize($account);
      }
      catch (\Throwable $e) {
        $this->getLogger('hotel_reservation')->error('Auto-login after password set failed: @message', [
          '@message' => $e->getMessage(),
        ]);
      }

      return new SymfonyJsonResponse([
        'success' => TRUE,
        'message' => $this->t('Пароль сохранён. Вы вошли в личный кабинет.'),
        'redirect' => '/hotel-reservation/my-bookings',
      ]);
    }
    catch (\Throwable $e) {
      $this->getLogger('hotel_reservation')->error('Failed to set guest password: @message', [
        '@message' => $e->getMessage(),
      ]);
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Не удалось сохранить пароль. Попробуйте ещё раз.',
      ], 500);
    }
  }

  /**
   * Ensures a hotel_client account for the booking guest.
   *
   * Matches by email first, then by phone digits as login. Creates a new
   * active account when nothing matches. Never throws: failures are logged
   * and the booking proceeds without an account.
   *
   * @param string $guest_name
   *   Guest name from the form.
   * @param string $guest_email
   *   Guest email from the form (may be empty).
   * @param string $guest_phone
   *   Guest phone from the form.
   *
   * @return array
   *   Associative array with keys: account (?\Drupal\user\UserInterface),
   *   created (bool), login_url (string, one-time login link or '').
   */
  protected function ensureGuestAccount(string $guest_name, string $guest_email, string $guest_phone): array {
    $result = ['account' => NULL, 'created' => FALSE, 'login_url' => ''];
    try {
      $user_storage = $this->entityTypeManager->getStorage('user');
      $digits = preg_replace('/\D/', '', $guest_phone);
      if (strlen($digits) === 11 && $digits[0] === '8') {
        $digits = '7' . substr($digits, 1);
      }
      $email_valid = $guest_email !== '' && \Drupal::service('email.validator')->isValid($guest_email);

      $account = NULL;
      if ($email_valid) {
        $found = $user_storage->loadByProperties(['mail' => $guest_email]);
        $account = $found ? reset($found) : NULL;
      }
      if (!$account && $digits !== '') {
        $found = $user_storage->loadByProperties(['name' => $digits]);
        $account = $found ? reset($found) : NULL;
      }

      if ($account) {
        if (!$account->hasRole('hotel_client')) {
          $account->addRole('hotel_client');
          $account->save();
        }
        $result['account'] = $account;
        try {
          $result['login_url'] = user_pass_reset_url($account)->toString();
        }
        catch (\Throwable $e) {
          $this->getLogger('hotel_reservation')->warning('Failed to build login URL for uid @uid: @message', [
            '@uid' => $account->id(),
            '@message' => $e->getMessage(),
          ]);
        }
        return $result;
      }

      if ($email_valid) {
        $username = $email = $guest_email;
      }
      elseif ($digits !== '') {
        $username = $digits;
        $site_mail = \Drupal::config('system.site')->get('mail') ?: '';
        $domain = 'example.com';
        if (preg_match('/@([^@\s]+)$/', $site_mail, $m)) {
          $domain = $m[1];
        }
        $email = $digits . '@' . $domain;
      }
      else {
        return $result;
      }

      $base_name = mb_substr($username, 0, 55);
      $username = $base_name;
      for ($i = 2; $user_storage->loadByProperties(['name' => $username]); $i++) {
        $username = mb_substr($base_name, 0, 55 - strlen((string) $i) - 1) . '_' . $i;
      }
      $base_mail = $email;
      for ($i = 2; $user_storage->loadByProperties(['mail' => $email]); $i++) {
        $parts = explode('@', $base_mail, 2);
        $email = $parts[0] . '_' . $i . '@' . $parts[1];
      }

      $account = $user_storage->create([
        'name' => $username,
        'mail' => $email,
        'pass' => \Drupal::service('password_generator')->generate(16),
        'status' => 1,
        'roles' => ['hotel_client'],
        'init' => $email,
      ]);
      $account->save();
      $result['account'] = $account;
      $result['created'] = TRUE;
      try {
        $result['login_url'] = user_pass_reset_url($account)->toString();
      }
      catch (\Throwable $e) {
        $this->getLogger('hotel_reservation')->warning('Failed to build login URL for new uid @uid: @message', [
          '@uid' => $account->id(),
          '@message' => $e->getMessage(),
        ]);
      }
      return $result;
    }
    catch (\Throwable $e) {
      $this->getLogger('hotel_reservation')->error('Failed to ensure guest account: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
    return $result;
  }

  /**
   * Returns price breakdown for a specific room.
   *
   * @param int $room_id
   *   The room entity ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request with check_in and check_out query params.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with price details.
   */
  public function getRoomPrices($room_id, Request $request) {
    $check_in = $request->query->get('check_in', '');
    $check_out = $request->query->get('check_out', '');

    if (empty($room_id) || empty($check_in) || empty($check_out)) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Укажите параметры room_id, check_in и check_out.',
      ], 400);
    }

    if (!$this->validateDate($check_in) || !$this->validateDate($check_out)) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Неверный формат даты. Используйте Г-М-Д.',
      ], 400);
    }

    if ($check_out <= $check_in) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Дата выезда должна быть позже даты заезда.',
      ], 400);
    }

    // Load the room.
    $room = $this->entityTypeManager->getStorage('hr_room')->load($room_id);
    if (!$room) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Номер не найден.',
      ], 404);
    }

    $pricing = hotel_reservation_calculate_price($room_id, $check_in, $check_out);
    $config = $this->config('hotel_reservation.settings');
    $currency = $config->get('currency_symbol') ?: '₽';

    // Format daily prices for the response.
    $daily = [];
    foreach ($pricing['daily_prices'] as $date => $price) {
      $daily[] = [
        'date' => $date,
        'price' => number_format($price, 2, '.', ''),
        'formatted' => number_format($price, 2) . ' ' . $currency,
      ];
    }

    return new SymfonyJsonResponse([
      'success' => TRUE,
      'room_id' => (int) $room_id,
      'room_name' => $room->label(),
      'base_price' => number_format($pricing['base_price'], 2, '.', ''),
      'nights' => $pricing['nights'],
      'total_price' => number_format($pricing['total'], 2, '.', ''),
      'formatted_total' => number_format($pricing['total'], 2) . ' ' . $currency,
      'currency' => $currency,
      'daily_prices' => $daily,
    ]);
  }

  public function getRoom($room_id) {
    $room = $this->entityTypeManager->getStorage('hr_room')->load($room_id);
    if (!$room || !$room->isPublished()) {
      return new SymfonyJsonResponse(['success' => FALSE, 'message' => 'Номер не найден.'], 404);
    }
    $config = $this->config('hotel_reservation.settings');
    $currency = $config->get('currency_symbol') ?: '₽';
    $typeLabel = $room->get('room_type')->value ?? 'standard';
    try {
      $allowed = function_exists('hotel_reservation_room_type_allowed_values') ? hotel_reservation_room_type_allowed_values() : [];
      if (!empty($allowed[$typeLabel])) {
        $typeLabel = $allowed[$typeLabel];
      }
    }
    catch (\Throwable $e) {
    }
    $amenities = [];
    $raw = $room->getAmenities();
    if (!empty($raw)) {
      $amenities = array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
    return new SymfonyJsonResponse([
      'success' => TRUE,
      'room' => [
        'id' => (int) $room->id(),
        'name' => $room->label(),
        'room_type' => $room->get('room_type')->value,
        'room_type_label' => $typeLabel,
        'capacity' => (int) $room->getCapacity(),
        'base_price' => number_format((float) $room->getBasePrice(), 2, '.', ''),
        'price_formatted' => number_format((float) $room->getBasePrice(), 0, '.', ' ') . ' ' . $currency,
        'teaser' => method_exists($room, 'getTeaserPlain') ? $room->getTeaserPlain(200) : '',
        'description' => method_exists($room, 'getDescriptionPlain') ? $room->getDescriptionPlain() : '',
        'amenities' => $amenities,
        'slides' => method_exists($room, 'getSliderImages') ? $room->getSliderImages() : [],
        'currency' => $currency,
      ],
    ]);
  }

  /**
   * Validates a date string in Y-m-d format.
   *
   * @param string $date
   *   The date string to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function validateDate(string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      return FALSE;
    }
    $parts = explode('-', $date);
    return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
  }

}
