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

    $form['notifications']['auto_create_guest_account'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Создавать учётную запись гостя'),
      '#description' => $this->t('При оформлении брони автоматически создавать учётную запись с ролью «Клиент отеля»: по email из заявки, а если email не указан — логином станет номер телефона. Гость увидит свои бронирования на странице «Мои бронирования».'),
      '#default_value' => $config->get('auto_create_guest_account') !== NULL ? (bool) $config->get('auto_create_guest_account') : TRUE,
    ];

    // ============================================================
    // Fieldset: Email texts
    // ============================================================
    $form['mail_texts'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Тексты писем'),
      '#description' => $this->t('Доступные токены: @guest, @email, @phone, @room, @check_in, @check_out, @count, @total, @currency, @notes, @hotel, @login_url (ссылка для разового входа гостя, если создана учётная запись).'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['mail_texts']['mail_admin_new_booking'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Письмо админу о новом бронировании'),
      '#default_value' => $config->get('mail_admin_new_booking') ?: hotel_reservation_default_mail_text('admin_new_booking'),
      '#rows' => 8,
    ];

    $form['mail_texts']['mail_guest_pending'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Письмо клиенту при оформлении заявки'),
      '#default_value' => $config->get('mail_guest_pending') ?: hotel_reservation_default_mail_text('guest_pending'),
      '#rows' => 8,
    ];

    $form['mail_texts']['mail_guest_confirmed'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Письмо клиенту о подтверждении'),
      '#default_value' => $config->get('mail_guest_confirmed') ?: hotel_reservation_default_mail_text('guest_confirmed'),
      '#rows' => 8,
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
      '#description' => $this->t('Ширина всплывающей расширенной карточки при клике (50–95%, по умолчанию 65). Высота ограничена 70% экрана.'),
      '#default_value' => $config->get('room_modal_width') ?: 65,
      '#required' => TRUE,
      '#min' => 50,
      '#max' => 95,
    ];

    $form['client_role'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Доступ для владельца и гостей'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#description' => $this->t('Роль «Владелец отеля»: календарь, аналитика, панель, просмотр бронирований и изменение их статусов — без доступа к номерам и настройкам. Роль «Клиент отеля»: гость видит только свои бронирования на странице «Мои бронирования».'),
    ];

    $role_storage = \Drupal::entityTypeManager()->getStorage('user_role');
    $owner_exists = (bool) $role_storage->load('hotel_owner');
    $guest_exists = (bool) $role_storage->load('hotel_client');
    $forbidden = \Drupal\hotel_reservation\Access\HotelReservationAccessCheck::revokedAdminPermissions();
    $bad_roles = [];
    foreach (['hotel_owner' => 'Владелец отеля', 'hotel_client' => 'Клиент отеля'] as $role_id => $role_label) {
      $role = $role_storage->load($role_id);
      if ($role && array_intersect($forbidden, $role->getPermissions())) {
        $bad_roles[] = $role_label;
      }
    }
    if (!empty($bad_roles)) {
      $form['client_role']['warning'] = [
        '#markup' => '<p><strong>' . $this->t('У ролей (@roles) есть лишние админ-права (тулбар, страницы администрирования). Просто сохраните эту форму — права будут сняты автоматически.', [
          '@roles' => implode(', ', $bad_roles),
        ]) . '</strong></p>',
      ];
    }
    if ($owner_exists && $guest_exists) {
      $form['client_role']['info'] = [
        '#markup' => '<p>' . $this->t('Обе роли существуют. Создайте пользователей на странице <a href=":url">Люди → Добавить пользователя</a> и назначьте им нужную роль.', [
          ':url' => \Drupal\Core\Url::fromRoute('user.admin_create')->toString(),
        ]) . '</p>',
      ];
    }
    else {
      $form['client_role']['info'] = [
        '#markup' => '<p>' . $this->t('Одна из ролей не найдена. Пересохраните настройки или выполните обновления базы — роли создадутся автоматически.') . '</p>',
      ];
    }

    $form['system'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Состояние системы'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#description' => $this->t('Если после обновления модуля доступ для владельца не меняется — нажмите кнопку ниже. Она сбрасывает PHP OPcache и пересобирает маршруты.'),
    ];

    $opcache_status = FALSE;
    if (function_exists('opcache_get_status')) {
      try {
        $status = @opcache_get_status(FALSE);
        $opcache_status = !empty($status['opcache_enabled']);
      }
      catch (\Throwable $e) {
        $opcache_status = FALSE;
      }
    }
    $form['system']['opcache_status'] = [
      '#markup' => '<p>' . ($opcache_status ? $this->t('PHP OPcache включён.') : $this->t('PHP OPcache выключен или недоступен.')) . '</p>',
    ];
    $weekday_ok = TRUE;
    try {
      $schema = \Drupal::database()->schema();
      foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $suffix) {
        if (!$schema->fieldExists('hr_room', 'price_' . $suffix . '__value')) {
          $weekday_ok = FALSE;
          break;
        }
      }
    }
    catch (\Throwable $e) {
      $weekday_ok = FALSE;
    }
    $form['system']['weekday_storage'] = [
      '#markup' => '<p>' . ($weekday_ok ? $this->t('Колонки цен по дням недели: OK.') : $this->t('Колонок цен по дням недели НЕТ — выполните обновления базы (хук 10019). Без них цены не сохраняются.')) . '</p>',
    ];
    $form['system']['rebuild'] = [
      '#type' => 'submit',
      '#value' => $this->t('Сбросить OPcache и пересобрать роуты'),
      '#submit' => [[$this, 'rebuildCachesSubmit']],
      '#limit_validation_errors' => [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Resets PHP OPcache and rebuilds routes.
   */
  public function rebuildCachesSubmit(array &$form, FormStateInterface $form_state) {
    if (function_exists('opcache_reset')) {
      try {
        if (@opcache_reset()) {
          $this->messenger()->addStatus($this->t('OPcache сброшен.'));
        }
        else {
          $this->messenger()->addWarning($this->t('Не удалось сбросить OPcache (возможно, запрещён настройками сервера).'));
        }
      }
      catch (\Throwable $e) {
        $this->messenger()->addWarning($e->getMessage());
      }
    }
    else {
      $this->messenger()->addWarning($this->t('OPcache недоступен.'));
    }
    try {
      \Drupal::service('router.builder')->rebuild();
      $this->messenger()->addStatus($this->t('Маршруты пересобраны. Проверьте доступ под владельцем.'));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }
    $form_state->setRedirect('hotel_reservation.settings');
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
      ->set('auto_create_guest_account', (bool) $form_state->getValue('auto_create_guest_account'))
      ->set('mail_admin_new_booking', trim((string) $form_state->getValue('mail_admin_new_booking')))
      ->set('mail_guest_pending', trim((string) $form_state->getValue('mail_guest_pending')))
      ->set('mail_guest_confirmed', trim((string) $form_state->getValue('mail_guest_confirmed')))
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
      ->set('room_modal_width', max(50, min(95, (int) $form_state->getValue('room_modal_width') ?: 65)))
      ->save();

    // (Re)create both hotel roles with correct permissions.
    $role_storage = \Drupal::entityTypeManager()->getStorage('user_role');
    $roles = [
      'hotel_owner' => [
        'label' => 'Владелец отеля',
        'permissions' => \Drupal\hotel_reservation\Access\HotelReservationAccessCheck::clientPermissions(),
      ],
      'hotel_client' => [
        'label' => 'Клиент отеля',
        'permissions' => \Drupal\hotel_reservation\Access\HotelReservationAccessCheck::guestPermissions(),
      ],
    ];
    foreach ($roles as $id => $definition) {
      $role = $role_storage->load($id);
      if (!$role) {
        $role = $role_storage->create([
          'id' => $id,
          'label' => $definition['label'],
        ]);
      }
      foreach ($definition['permissions'] as $permission) {
        $role->grantPermission($permission);
      }
      foreach (\Drupal\hotel_reservation\Access\HotelReservationAccessCheck::revokedAdminPermissions() as $permission) {
        $role->revokePermission($permission);
      }
      $role->save();
    }

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
