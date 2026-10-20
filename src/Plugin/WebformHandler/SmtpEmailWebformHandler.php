<?php

namespace Drupal\hotel_reservation\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\Utility\WebformElementHelper;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Sends a webform submission through the hotel module's own SMTP transport.
 *
 * This handler mirrors the "Send email" handler but does not use the Drupal
 * mail manager, so delivery does not depend on the site mail backend, the
 * hosting relay or the sending domain configuration.
 *
 * @WebformHandler(
 *   id = "hotel_reservation_smtp_email",
 *   label = @Translation("Email via hotel SMTP"),
 *   category = @Translation("Notification"),
 *   description = @Translation("Sends the submission through the SMTP server configured in Hotel Reservation settings, independent of the site mail backend."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = TRUE,
 *   conditions = TRUE,
 * )
 */
class SmtpEmailWebformHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'to' => '[site:mail]',
      'from_email' => '',
      'from_name' => '',
      'subject' => '[webform:title] вЂ” РЅРѕРІР°СЏ Р·Р°СЏРІРєР°',
      'body' => "РќРѕРІР°СЏ Р·Р°СЏРІРєР° СЃ С„РѕСЂРјС‹ В«[webform:title]В».\n\n[webform_submission:values]",
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#markup' => '<div class="webform-handler-summary">' . $this->t('To: @to<br>Subject: @subject', [
        '@to' => $this->configuration['to'],
        '@subject' => $this->configuration['subject'],
      ]) . '</div>',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $this->applyFormStateToConfiguration($form_state);

    $config_link = Url::fromRoute('hotel_reservation.settings')->toString();
    $smtp_enabled = (bool) $this->configFactory->get('hotel_reservation.settings')->get('smtp_enabled');

    if (!$smtp_enabled) {
      $form['smtp_warning'] = [
        '#type' => 'webform_message',
        '#message_type' => 'warning',
        '#message_message' => $this->t('The module SMTP transport is disabled. Enable and configure it in <a href=":url">Hotel Reservation settings</a>, otherwise this handler falls back to the site mail backend.', [
          ':url' => $config_link,
        ]),
      ];
    }

    $form['to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('To email'),
      '#description' => $this->t('One or more addresses, comma separated. Tokens are allowed, e.g. [webform_submission:values:email].'),
      '#default_value' => $this->configuration['to'],
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['from_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From email (optional)'),
      '#description' => $this->t('Leave empty to use the sender from Hotel Reservation settings.'),
      '#default_value' => $this->configuration['from_email'],
      '#maxlength' => 255,
    ];

    $form['from_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From name (optional)'),
      '#description' => $this->t('Leave empty to use the sender name from Hotel Reservation settings.'),
      '#default_value' => $this->configuration['from_name'],
      '#maxlength' => 255,
    ];

    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#default_value' => $this->configuration['subject'],
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#description' => $this->t('Plain text. Tokens are allowed. [webform_submission:values] prints all submitted values.'),
      '#default_value' => $this->configuration['body'],
      '#required' => TRUE,
      '#rows' => 12,
    ];

    $form['token_tree_link'] = $this->buildTokenTreeElement(['webform', 'webform_submission']);
    $form['smtp_settings_link'] = [
      '#markup' => '<p>' . $this->t('<a href=":url">Open SMTP settings</a>', [':url' => $config_link]) . '</p>',
    ];

    WebformElementHelper::convertRenderMarkupToStrings($form);
    $this->elementTokenValidate($form, ['webform', 'webform_submission']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $to = trim((string) $form_state->getValue('to'));
    if ($to === '') {
      $form_state->setErrorByName('to', $this->t('Specify at least one recipient.'));
    }

    $from_email = trim((string) $form_state->getValue('from_email'));
    if ($from_email !== '' && strpos($from_email, '[') === FALSE && !\Drupal::service('email.validator')->isValid($from_email)) {
      $form_state->setErrorByName('from_email', $this->t('The From email address is not valid.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $values = $form_state->getValues();
    foreach (array_keys($this->defaultConfiguration()) as $key) {
      if (array_key_exists($key, $values)) {
        $this->configuration[$key] = trim((string) $values[$key]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $to = trim((string) $this->replaceTokens($this->configuration['to'], $webform_submission));
    $subject = trim((string) $this->replaceTokens($this->configuration['subject'], $webform_submission));
    $body = (string) $this->replaceTokens($this->configuration['body'], $webform_submission);

    if ($to === '') {
      $this->getLogger('hotel_reservation')->error('SMTP webform handler @id: no recipient for submission @sid.', [
        '@id' => $this->getHandlerId(),
        '@sid' => $webform_submission->id(),
      ]);
      return;
    }

    $overrides = [];
    $from_email = trim((string) $this->replaceTokens($this->configuration['from_email'], $webform_submission));
    $from_name = trim((string) $this->replaceTokens($this->configuration['from_name'], $webform_submission));
    if ($from_email !== '') {
      $overrides['smtp_from_email'] = $from_email;
    }
    if ($from_name !== '') {
      $overrides['smtp_from_name'] = $from_name;
    }

    $settings = $this->configFactory->get('hotel_reservation.settings');
    if (!(bool) $settings->get('smtp_enabled') || trim((string) $settings->get('smtp_host')) === '') {
      $this->getLogger('hotel_reservation')->warning('SMTP is not enabled in Hotel Reservation settings; handler @id skipped for submission @sid.', [
        '@id' => $this->getHandlerId(),
        '@sid' => $webform_submission->id(),
      ]);
      return;
    }

    try {
      \Drupal::service('hotel_reservation.smtp_sender')->send($to, $subject, $body, $overrides);
      $this->getLogger('hotel_reservation')->notice('Submission @sid sent to @to via hotel SMTP (handler @id).', [
        '@sid' => $webform_submission->id(),
        '@to' => $to,
        '@id' => $this->getHandlerId(),
      ]);
    }
    catch (\Throwable $e) {
      $this->getLogger('hotel_reservation')->error('Submission @sid was not sent via hotel SMTP: @message', [
        '@sid' => $webform_submission->id(),
        '@message' => $e->getMessage(),
      ]);
    }
  }

}

