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
      '#title' => $this->t('Информация об отеле'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['hotel_info']['hotel_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Название отеля'),
      '#description' => $this->t('Название отеля для писем и уведомлений. Оставьте пустым, чтобы использовать имя сайта.'),
      '#default_value' => $config->get('hotel_name') ?: $site_name,
      '#required' => FALSE,
      '#maxlength' => 255,
    ];

    $form['hotel_info']['currency_symbol'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Символ валюты'),
      '#description' => $this->t('Символ, отображаемый рядом с ценами (напр. ₽, $, €).'),
      '#default_value' => $config->get('currency_symbol') ?: '₽',
      '#required' => TRUE,
      '#maxlength' => 10,
      '#size' => 10,
    ];

    $form['hotel_info']['currency_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Код валюты'),
      '#description' => $this->t('Код валюты ISO 4217 (напр. RUB, USD, EUR).'),
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
      '#title' => $this->t('Правила бронирования'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['booking_rules']['min_stay_nights'] = [
      '#type' => 'number',
      '#title' => $this->t('Минимальное количество ночей'),
      '#description' => $this->t('Минимальное количество ночей для бронирования.'),
      '#default_value' => $config->get('min_stay_nights') ?: 1,
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 9999,
    ];

    $form['booking_rules']['max_stay_nights'] = [
      '#type' => 'number',
      '#title' => $this->t('Максимальное количество ночей'),
      '#description' => $this->t('Максимальное количество ночей для бронирования.'),
      '#default_value' => $config->get('max_stay_nights') ?: 30,
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 9999,
    ];

    $form['booking_rules']['check_in_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Время заезда'),
      '#description' => $this->t('Стандартное время заезда (напр. 14:00).'),
      '#default_value' => $config->get('check_in_time') ?: '14:00',
      '#required' => TRUE,
      '#maxlength' => 5,
      '#size' => 10,
      '#attributes' => ['placeholder' => 'ЧЧ:ММ'],
    ];

    $form['booking_rules']['check_out_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Время выезда'),
      '#description' => $this->t('Стандартное время выезда (напр. 12:00).'),
      '#default_value' => $config->get('check_out_time') ?: '12:00',
      '#required' => TRUE,
      '#maxlength' => 5,
      '#size' => 10,
      '#attributes' => ['placeholder' => 'ЧЧ:ММ'],
    ];

    $form['booking_rules']['booking_conditions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Условия бронирования'),
      '#description' => $this->t('Условия бронирования, отображаемые в форме. Оставьте пустым для значений по умолчанию.'),
      '#default_value' => $config->get('booking_conditions') ?: $this->getDefaultBookingConditions(),
      '#required' => FALSE,
      '#rows' => 6,
    ];

    // ============================================================
    // Fieldset: Notifications
    // ============================================================
    $form['notifications'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Уведомления'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['notifications']['enable_admin_notification'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Уведомлять администратора'),
      '#description' => $this->t('Отправлять письмо администратору при новом бронировании.'),
      '#default_value' => $config->get('enable_admin_notification') !== NULL ? (bool) $config->get('enable_admin_notification') : TRUE,
    ];

    $form['notifications']['admin_notification_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email администратора'),
      '#description' => $this->t('Адрес email для получения уведомлений. Оставьте пустым для отключения.'),
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
      '#title' => $this->t('Отправлять подтверждение гостю'),
      '#description' => $this->t('Отправлять письмо с подтверждением гостю при подтверждении бронирования.'),
      '#default_value' => $config->get('enable_guest_confirmation') !== NULL ? (bool) $config->get('enable_guest_confirmation') : TRUE,
    ];

    // ============================================================
    // Fieldset: Automation
    // ============================================================
    $form['automation'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Автоматизация'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['automation']['reservation_expiration_hours'] = [
      '#type' => 'number',
      '#title' => $this->t('Срок действия заявки (часы)'),
      '#description' => $this->t('Неподтверждённые заявки автоматически истекут через указанное количество часов. Проверяется при запуске cron.'),
      '#default_value' => $config->get('reservation_expiration_hours') ?: 24,
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 720,
    ];

    // ============================================================
    // Fieldset: Booking Form Design
    // ============================================================
    $form['form_design'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Дизайн формы бронирования'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['form_design']['form_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Заголовок формы'),
      '#description' => $this->t('Оставьте пустым для названия отеля.'),
      '#default_value' => $config->get('form_title') ?: '',
      '#maxlength' => 255,
    ];

    $form['form_design']['form_subtitle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Подзаголовок формы'),
      '#description' => $this->t('Текст под заголовком. Пусто = время заезда/выезда.'),
      '#default_value' => $config->get('form_subtitle') ?: '',
      '#maxlength' => 255,
    ];

    $form['form_design']['form_button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Текст на кнопке отправки'),
      '#default_value' => $config->get('form_button_text') ?: $this->t('Забронировать'),
      '#maxlength' => 50,
    ];

    $form['form_design']['form_primary_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Основной цвет'),
      '#description' => $this->t('Цвет кнопок и акцентов формы.'),
      '#default_value' => $config->get('form_primary_color') ?: '#d97706',
    ];

    $form['form_design']['form_background_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Цвет фона формы'),
      '#description' => $this->t('Фон формы бронирования.'),
      '#default_value' => $config->get('form_background_color') ?: '#ffffff',
    ];

    $form['form_design']['form_text_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Цвет текста'),
      '#description' => $this->t('Основной цвет текста.'),
      '#default_value' => $config->get('form_text_color') ?: '#1a1a2e',
    ];

    $form['form_design']['form_border_radius'] = [
      '#type' => 'number',
      '#title' => $this->t('Скругление углов (px)'),
      '#description' => $this->t('Скругление полей и кнопок.'),
      '#default_value' => $config->get('form_border_radius') ?: 10,
      '#min' => 0,
      '#max' => 30,
    ];

    $form['form_design']['form_success_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Заголовок успешной заявки'),
      '#default_value' => $config->get('form_success_title') ?: $this->t('Заявка отправлена!'),
      '#maxlength' => 100,
    ];

    $form['form_design']['form_success_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Текст успешной заявки'),
      '#description' => $this->t('Используйте @id как плейсхолдер номера бронирования.'),
      '#default_value' => $config->get('form_success_text') ?: $this->t('Ваша заявка #@id ожидает подтверждения. Мы свяжемся с вами в ближайшее время.'),
      '#maxlength' => 255,
    ];

    $form['rooms'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Карточки номеров'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['rooms']['room_modal_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Ширина модального окна номера (%)'),
      '#description' => $this->t('Ширина всплывающей расширенной карточки при клике (50–95%, по умолчанию 80).'),
      '#default_value' => $config->get('room_modal_width') ?: 80,
      '#required' => TRUE,
      '#min' => 50,
      '#max' => 95,
    ];

    $form['share'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Доступ для клиента (поделиться статистикой)'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#description' => $this->t('Создайте секретную ссылку для клиента, чтобы он видел календарь/аналитику/панель без доступа в админку. Ссылка защищена паролем и закрыта от индексации.'),
    ];

    $share_enabled = (bool) $config->get('share_enabled');
    $share_token = $config->get('share_token') ?: bin2hex(random_bytes(16));
    $share_password = $config->get('share_password') ?: '';

    $form['share']['share_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Включить общий доступ'),
      '#default_value' => $share_enabled,
    ];

    $form['share']['share_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Секретный токен (часть URL)'),
      '#description' => $this->t('Только латиница, цифры, дефис и подчёркивание. Сгенерируйте случайный.'),
      '#default_value' => $share_token,
      '#required' => FALSE,
      '#pattern' => '[a-zA-Z0-9_-]+',
      '#states' => [
        'visible' => [':input[name="share_enabled"]' => ['checked' => TRUE]],
        'required' => [':input[name="share_enabled"]' => ['checked' => TRUE]],
      ],
    ];

    $form['share']['share_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Пароль для клиента'),
      '#description' => $this->t('Оставьте пустым — тогда доступ только по секретной ссылке. Если заполнить — потребуется ввод пароля.'),
      '#default_value' => $share_password,
      '#states' => [
        'visible' => [':input[name="share_enabled"]' => ['checked' => TRUE]],
      ],
    ];

    if ($share_enabled && $share_token) {
      $base = \Drupal::request()->getSchemeAndHttpHost();
      $urls = [
        'dashboard' => $base . '/hotel-reservation/share/' . $share_token . '/dashboard',
        'analytics' => $base . '/hotel-reservation/share/' . $share_token . '/analytics',
        'calendar' => $base . '/hotel-reservation/share/' . $share_token . '/calendar',
      ];
      $form['share']['share_links'] = [
        '#type' => 'details',
        '#title' => $this->t('Ссылки для клиента'),
        '#open' => TRUE,
      ];
      $form['share']['share_links']['info'] = [
        '#markup' => '<p>' . $this->t('Скопируйте и отправьте клиенту. Страницы закрыты от индексации (<code>noindex,nofollow</code>).') . '</p>' .
          '<ul><li><strong>Панель:</strong> <a href="' . $urls['dashboard'] . '" target="_blank">' . $urls['dashboard'] . '</a></li>' .
          '<li><strong>Аналитика:</strong> <a href="' . $urls['analytics'] . '" target="_blank">' . $urls['analytics'] . '</a></li>' .
          '<li><strong>Календарь:</strong> <a href="' . $urls['calendar'] . '" target="_blank">' . $urls['calendar'] . '</a> (/9/2026 — пример)</li></ul>',
      ];
    }

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
      $form_state->setErrorByName('max_stay_nights', $this->t('Максимум ночей не может быть меньше минимума.'));
    }

    // Validate time format HH:MM.
    $check_in_time = $form_state->getValue('check_in_time');
    if (!preg_match('/^\d{1,2}:\d{2}$/', $check_in_time)) {
      $form_state->setErrorByName('check_in_time', $this->t('Время заезда должно быть в формате ЧЧ:ММ.'));
    }
    else {
      $parts = explode(':', $check_in_time);
      if ((int) $parts[0] > 23 || (int) $parts[1] > 59) {
        $form_state->setErrorByName('check_in_time', $this->t('Время заезда указано неверно.'));
      }
    }

    $check_out_time = $form_state->getValue('check_out_time');
    if (!preg_match('/^\d{1,2}:\d{2}$/', $check_out_time)) {
      $form_state->setErrorByName('check_out_time', $this->t('Время выезда должно быть в формате ЧЧ:ММ.'));
    }
    else {
      $parts = explode(':', $check_out_time);
      if ((int) $parts[0] > 23 || (int) $parts[1] > 59) {
        $form_state->setErrorByName('check_out_time', $this->t('Время выезда указано неверно.'));
      }
    }

    // Validate admin email if notifications enabled.
    $enable_admin = (bool) $form_state->getValue('enable_admin_notification');
    $admin_email = $form_state->getValue('admin_notification_email');
    if ($enable_admin && empty($admin_email)) {
      $form_state->setErrorByName('admin_notification_email', $this->t('Укажите email администратора, если уведомления включены.'));
    }

    if ((bool) $form_state->getValue('share_enabled')) {
      $token = trim((string) $form_state->getValue('share_token'));
      if ($token === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $token)) {
        $form_state->setErrorByName('share_token', $this->t('Токен обязателен и только a-z, 0-9, _-.'));
      }
    }

    $modal_width = (int) $form_state->getValue('room_modal_width');
    if ($modal_width < 50 || $modal_width > 95) {
      $form_state->setErrorByName('room_modal_width', $this->t('Ширина должна быть от 50 до 95%.'));
    }
  }

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
      ->set('form_title', $form_state->getValue('form_title'))
      ->set('form_subtitle', $form_state->getValue('form_subtitle'))
      ->set('form_button_text', $form_state->getValue('form_button_text'))
      ->set('form_primary_color', $form_state->getValue('form_primary_color'))
      ->set('form_background_color', $form_state->getValue('form_background_color'))
      ->set('form_text_color', $form_state->getValue('form_text_color'))
      ->set('form_border_radius', (int) $form_state->getValue('form_border_radius'))
      ->set('form_success_title', $form_state->getValue('form_success_title'))
      ->set('form_success_text', $form_state->getValue('form_success_text'))
      ->set('share_enabled', (bool) $form_state->getValue('share_enabled'))
      ->set('share_token', trim((string) $form_state->getValue('share_token')))
      ->set('share_password', trim((string) $form_state->getValue('share_password')))
      ->set('room_modal_width', max(50, min(95, (int) $form_state->getValue('room_modal_width') ?: 80)))
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
      "1. Заезд начинается в 14:00, выезд — до 12:00.\n" .
      "2. Ранний заезд или поздний выезд возможен при наличии свободных мест и может требовать дополнительной оплаты.\n" .
      "3. Отмена бронирования должна быть произведена не менее чем за 24 часа до заезда.\n" .
      "4. Отель не несёт ответственности за потерю или повреждение личных вещей.\n" .
      "5. Все гости должны предъявить документ, удостоверяющий личность, при заезде.\n" .
      "6. Домашние животные не допускаются без предварительной договорённости."
    );
  }

}
