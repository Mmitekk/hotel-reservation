<?php

namespace Drupal\hotel_reservation\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for hotel_reservation.settings.
 *
 * @ingroup hotel_reservation
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['hotel_reservation.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hotel_reservation_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('hotel_reservation.settings');
    $site_name = \Drupal::config('system.site')->get('name') ?: '';

    // ============================================================
    // Fieldset: Hotel Information
    // ============================================================
    $form['hotel_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Hotel Information'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['hotel_info']['hotel_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Hotel Name'),
      '#description' => $this->t('The hotel name used in emails and notifications. Leave blank to use the site name.'),
      '#default_value' => $config->get('hotel_name') ?: $site_name,
      '#required' => FALSE,
      '#maxlength' => 255,
    ];

    $form['hotel_info']['currency_symbol'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Currency Symbol'),
      '#description' => $this->t('The symbol displayed next to prices (e.g. ₽, $, €).'),
      '#default_value' => $config->get('currency_symbol') ?: '₽',
      '#required' => TRUE,
      '#maxlength' => 10,
      '#size' => 10,
    ];

    $form['hotel_info']['currency_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Currency Code'),
      '#description' => $this->t('ISO 4217 currency code (e.g. RUB, USD, EUR).'),
      '#default_value' => $config->get('currency_code') ?: 'RUB',
      '#required' => TRUE,
      '#maxlength' => 3,
      '#size' => 10,
    ];

    // ============================================================
    // Fieldset: Booking Rules
    // ============================================================
    $form['booking_rules'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Booking Rules'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['booking_rules']['min_stay_nights'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum Stay (nights)'),
      '#description' => $this->t('The minimum number of nights for a reservation.'),
      '#default_value' => $config->get('min_stay_nights') ?: 1,
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 365,
    ];

    $form['booking_rules']['max_stay_nights'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Stay (nights)'),
      '#description' => $this->t('The maximum number of nights for a reservation.'),
      '#default_value' => $config->get('max_stay_nights') ?: 30,
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 365,
    ];

    $form['booking_rules']['check_in_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Standard Check-in Time'),
      '#description' => $this->t('The standard check-in time displayed to guests (e.g. 14:00).'),
      '#default_value' => $config->get('check_in_time') ?: '14:00',
      '#required' => TRUE,
      '#maxlength' => 5,
      '#size' => 10,
      '#attributes' => ['placeholder' => 'HH:MM'],
    ];

    $form['booking_rules']['check_out_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Standard Check-out Time'),
      '#description' => $this->t('The standard check-out time displayed to guests (e.g. 12:00).'),
      '#default_value' => $config->get('check_out_time') ?: '12:00',
      '#required' => TRUE,
      '#maxlength' => 5,
      '#size' => 10,
      '#attributes' => ['placeholder' => 'HH:MM'],
    ];

    $form['booking_rules']['booking_conditions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Booking Conditions'),
      '#description' => $this->t('The terms and conditions displayed on the booking form. Leave blank to use the default.'),
      '#default_value' => $config->get('booking_conditions') ?: $this->getDefaultBookingConditions(),
      '#required' => FALSE,
      '#rows' => 6,
    ];

    // ============================================================
    // Fieldset: Notifications
    // ============================================================
    $form['notifications'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Notifications'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['notifications']['enable_admin_notification'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Admin Notification'),
      '#description' => $this->t('Send an email to the administrator when a new reservation is created.'),
      '#default_value' => $config->get('enable_admin_notification') !== NULL ? (bool) $config->get('enable_admin_notification') : TRUE,
    ];

    $form['notifications']['admin_notification_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Admin Notification Email'),
      '#description' => $this->t('The email address to receive new reservation notifications. Leave blank to disable admin notifications.'),
      '#default_value' => $config->get('admin_notification_email') ?: '',
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="enable_admin_notification"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['notifications']['enable_guest_confirmation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Guest Confirmation Email'),
      '#description' => $this->t('Send a confirmation email to the guest when their reservation is confirmed.'),
      '#default_value' => $config->get('enable_guest_confirmation') !== NULL ? (bool) $config->get('enable_guest_confirmation') : TRUE,
    ];

    // ============================================================
    // Fieldset: Automation
    // ============================================================
    $form['automation'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Automation'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['automation']['reservation_expiration_hours'] = [
      '#type' => 'number',
      '#title' => $this->t('Reservation Expiration (hours)'),
      '#description' => $this->t('Pending (unconfirmed) reservations will automatically be set to expired after this many hours. Checked during cron runs.'),
      '#default_value' => $config->get('reservation_expiration_hours') ?: 24,
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 720,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $min_stay = (int) $form_state->getValue('min_stay_nights');
    $max_stay = (int) $form_state->getValue('max_stay_nights');

    if ($max_stay < $min_stay) {
      $form_state->setErrorByName('max_stay_nights', $this->t('Maximum stay cannot be less than minimum stay.'));
    }

    // Validate time format HH:MM.
    $check_in_time = $form_state->getValue('check_in_time');
    if (!preg_match('/^\d{1,2}:\d{2}$/', $check_in_time)) {
      $form_state->setErrorByName('check_in_time', $this->t('Check-in time must be in HH:MM format.'));
    }
    else {
      $parts = explode(':', $check_in_time);
      if ((int) $parts[0] > 23 || (int) $parts[1] > 59) {
        $form_state->setErrorByName('check_in_time', $this->t('Check-in time is not valid.'));
      }
    }

    $check_out_time = $form_state->getValue('check_out_time');
    if (!preg_match('/^\d{1,2}:\d{2}$/', $check_out_time)) {
      $form_state->setErrorByName('check_out_time', $this->t('Check-out time must be in HH:MM format.'));
    }
    else {
      $parts = explode(':', $check_out_time);
      if ((int) $parts[0] > 23 || (int) $parts[1] > 59) {
        $form_state->setErrorByName('check_out_time', $this->t('Check-out time is not valid.'));
      }
    }

    // Validate admin email if notifications enabled.
    $enable_admin = (bool) $form_state->getValue('enable_admin_notification');
    $admin_email = $form_state->getValue('admin_notification_email');
    if ($enable_admin && empty($admin_email)) {
      $form_state->setErrorByName('admin_notification_email', $this->t('Admin notification email is required when admin notifications are enabled.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('hotel_reservation.settings')
      ->set('hotel_name', $form_state->getValue('hotel_name'))
      ->set('currency_symbol', $form_state->getValue('currency_symbol'))
      ->set('currency_code', $form_state->getValue('currency_code'))
      ->set('min_stay_nights', (int) $form_state->getValue('min_stay_nights'))
      ->set('max_stay_nights', (int) $form_state->getValue('max_stay_nights'))
      ->set('check_in_time', $form_state->getValue('check_in_time'))
      ->set('check_out_time', $form_state->getValue('check_out_time'))
      ->set('booking_conditions', $form_state->getValue('booking_conditions'))
      ->set('enable_admin_notification', (bool) $form_state->getValue('enable_admin_notification'))
      ->set('admin_notification_email', $form_state->getValue('admin_notification_email'))
      ->set('enable_guest_confirmation', (bool) $form_state->getValue('enable_guest_confirmation'))
      ->set('reservation_expiration_hours', (int) $form_state->getValue('reservation_expiration_hours'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Returns the default booking conditions text.
   *
   * @return string
   *   The default booking conditions.
   */
  protected function getDefaultBookingConditions(): string {
    return $this->t(
      "1. Check-in time starts at 14:00, check-out time ends at 12:00.
2. Early check-in or late check-out is subject to availability and may incur additional charges.
3. Cancellation must be made at least 24 hours before check-in to avoid penalties.
4. The hotel is not responsible for the loss or damage of personal belongings.
5. All guests must present a valid ID upon check-in.
6. Pets are not allowed unless prior arrangements have been made."
    );
  }

}
