<?php

namespace Drupal\hotel_reservation\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Form handler for the hr_reservation entity.
 *
 * @ingroup hotel_reservation
 */
class ReservationForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $entity = $this->getEntity();

    // --- Make total_price read-only (computed field). ---
    if (isset($form['total_price']['widget'][0]['value'])) {
      $form['total_price']['widget'][0]['value']['#disabled'] = TRUE;
      $form['total_price']['widget'][0]['value']['#description'] = $this->t('Это значение рассчитывается автоматически на основе номера, дат заезда и выезда.');
    }

    // --- Add AJAX triggers on room_id, check_in, check_out. ---
    $ajax_wrapper = 'price-breakdown-wrapper';

    $form[$ajax_wrapper] = [
      '#type' => 'container',
      '#attributes' => ['id' => $ajax_wrapper],
      '#weight' => 10,
    ];

    // Attach AJAX to room_id field.
    if (isset($form['room_id']['widget'][0]['target_id'])) {
      $form['room_id']['widget'][0]['target_id']['#ajax'] = [
        'callback' => '::recalculatePrice',
        'wrapper' => $ajax_wrapper,
        'event' => 'change',
        'progress' => ['type' => 'throbber', 'message' => $this->t('Расчёт цены...')],
      ];
    }

    // Attach AJAX to check_in field.
    if (isset($form['check_in']['widget'][0]['value'])) {
      $form['check_in']['widget'][0]['value']['#ajax'] = [
        'callback' => '::recalculatePrice',
        'wrapper' => $ajax_wrapper,
        'event' => 'change',
        'progress' => ['type' => 'throbber', 'message' => $this->t('Расчёт цены...')],
      ];
    }

    // Attach AJAX to check_out field.
    if (isset($form['check_out']['widget'][0]['value'])) {
      $form['check_out']['widget'][0]['value']['#ajax'] = [
        'callback' => '::recalculatePrice',
        'wrapper' => $ajax_wrapper,
        'event' => 'change',
        'progress' => ['type' => 'throbber', 'message' => $this->t('Расчёт цены...')],
      ];
    }

    // Build the initial price breakdown.
    $price_breakdown = $this->buildPriceBreakdown($form_state);
    $form[$ajax_wrapper] = array_merge($form[$ajax_wrapper], $price_breakdown);

    return $form;
  }

  /**
   * AJAX callback to recalculate price.
   */
  public function recalculatePrice(array &$form, FormStateInterface $form_state) {
    return $form['price-breakdown-wrapper'];
  }

  /**
   * Build the price breakdown table from current form values.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   A render array for the price breakdown.
   */
  protected function buildPriceBreakdown(FormStateInterface $form_state): array {
    $build = [];

    // Extract values from the form state.
    $room_id = $form_state->getValue(['room_id', 0, 'target_id']);
    $check_in_input = $form_state->getValue(['check_in', 0, 'value']);
    $check_out_input = $form_state->getValue(['check_out', 0, 'value']);

    // Also try user input for AJAX-triggered values.
    $user_input = $form_state->getUserInput();
    if (empty($room_id) && !empty($user_input['room_id'][0]['target_id'])) {
      $room_id = $user_input['room_id'][0]['target_id'];
    }

    // Convert check_in / check_out from DrupalDateTime array to Y-m-d.
    $check_in = NULL;
    $check_out = NULL;

    if (!empty($check_in_input)) {
      if (is_array($check_in_input)) {
        // datelist widget returns an array.
        $dt = new DrupalDateTime($check_in_input);
        $check_in = $dt->format('Y-m-d');
      }
      elseif ($check_in_input instanceof DrupalDateTime) {
        $check_in = $check_in_input->format('Y-m-d');
      }
      elseif (is_string($check_in_input)) {
        $check_in = (new DrupalDateTime($check_in_input))->format('Y-m-d');
      }
    }

    if (!empty($check_out_input)) {
      if (is_array($check_out_input)) {
        $dt = new DrupalDateTime($check_out_input);
        $check_out = $dt->format('Y-m-d');
      }
      elseif ($check_out_input instanceof DrupalDateTime) {
        $check_out = $check_out_input->format('Y-m-d');
      }
      elseif (is_string($check_out_input)) {
        $check_out = (new DrupalDateTime($check_out_input))->format('Y-m-d');
      }
    }

    if (empty($room_id) || empty($check_in) || empty($check_out)) {
      $build['info'] = [
        '#markup' => '<p class="price-info">' . $this->t('Выберите номер, дату заезда и выезда для расчёта цены.') . '</p>',
      ];
      return $build;
    }

    if ($check_out <= $check_in) {
      $build['info'] = [
        '#markup' => '<p class="price-info">' . $this->t('Дата выезда должна быть позже даты заезда.') . '</p>',
      ];
      return $build;
    }

    $pricing = hotel_reservation_calculate_price($room_id, $check_in, $check_out);

    if ($pricing['nights'] <= 0) {
      $build['info'] = [
        '#markup' => '<p class="price-info">' . $this->t('Не найдено ни одной ночи для выбранного периода.') . '</p>',
      ];
      return $build;
    }

    $config = \Drupal::config('hotel_reservation.settings');
    $currency = $config->get('currency_symbol') ?: '₽';
    $base_price = $pricing['base_price'] ?? 0;

    // Build the nightly breakdown table.
    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Дата'),
        $this->t('Цена (@currency)', ['@currency' => $currency]),
        $this->t('Разница'),
      ],
      '#caption' => $this->t('Подробности цены (@nights ночей)', ['@nights' => $pricing['nights']]),
      '#attributes' => ['class' => ['price-breakdown-table']],
    ];

    $row_index = 0;
    foreach ($pricing['daily_prices'] as $date => $price) {
      $formatted_date = (new \DateTime($date))->format('d.m.Y');
      $diff = $price - $base_price;

      $diff_markup = '';
      if (abs($diff) > 0.001) {
        if ($diff > 0) {
          $diff_markup = '<span class="price-increase">+' . number_format($diff, 2) . ' ' . $currency . '</span>';
        }
        else {
          $diff_markup = '<span class="price-decrease">' . number_format($diff, 2) . ' ' . $currency . '</span>';
        }
      }
      else {
        $diff_markup = '<span class="price-same">—</span>';
      }

      $build['table'][$row_index]['date'] = [
        '#markup' => $formatted_date,
      ];
      $build['table'][$row_index]['price'] = [
        '#markup' => number_format($price, 2) . ' ' . $currency,
      ];
      $build['table'][$row_index]['diff'] = [
        '#markup' => $diff_markup,
      ];
      $row_index++;
    }

    // Total row.
    $build['total'] = [
      '#markup' => '<div class="price-total"><strong>' . $this->t('Итого: @total @currency', [
        '@total' => number_format($pricing['total'], 2),
        '@currency' => $currency,
      ]) . '</strong></div>',
      '#weight' => 100,
    ];

    // Store total_price in form state for use during save.
    $form_state->set('calculated_total_price', number_format($pricing['total'], 2, '.', ''));

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $check_in_input = $form_state->getValue(['check_in', 0, 'value']);
    $check_out_input = $form_state->getValue(['check_out', 0, 'value']);

    $check_in = NULL;
    $check_out = NULL;

    if (!empty($check_in_input)) {
      if (is_array($check_in_input)) {
        $dt = new DrupalDateTime($check_in_input);
        $check_in = $dt->format('Y-m-d');
      }
      elseif ($check_in_input instanceof DrupalDateTime) {
        $check_in = $check_in_input->format('Y-m-d');
      }
      elseif (is_string($check_in_input)) {
        $check_in = (new DrupalDateTime($check_in_input))->format('Y-m-d');
      }
    }

    if (!empty($check_out_input)) {
      if (is_array($check_out_input)) {
        $dt = new DrupalDateTime($check_out_input);
        $check_out = $dt->format('Y-m-d');
      }
      elseif ($check_out_input instanceof DrupalDateTime) {
        $check_out = $check_out_input->format('Y-m-d');
      }
      elseif (is_string($check_out_input)) {
        $check_out = (new DrupalDateTime($check_out_input))->format('Y-m-d');
      }
    }

    if ($check_in && $check_out && $check_out <= $check_in) {
      $form_state->setErrorByName('check_out', $this->t('Дата выезда должна быть позже даты заезда.'));
    }

    // Validate minimum/maximum stay from config.
    if ($check_in && $check_out) {
      $config = \Drupal::config('hotel_reservation.settings');
      $min_stay = (int) $config->get('min_stay_nights') ?: 1;
      $max_stay = (int) $config->get('max_stay_nights') ?: 30;
      $nights = (new \DateTime($check_out))->diff(new \DateTime($check_in))->days;

      if ($nights < $min_stay) {
        $form_state->setErrorByName('check_out', $this->t('Минимальное количество ночей: @min.', ['@min' => $min_stay]));
      }
      if ($nights > $max_stay) {
        $form_state->setErrorByName('check_out', $this->t('Максимальное количество ночей: @max.', ['@max' => $max_stay]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Set the calculated total price on the entity before saving.
    $calculated_total = $form_state->get('calculated_total_price');
    if ($calculated_total !== NULL) {
      $this->entity->set('total_price', $calculated_total);
    }
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $status = $entity->save();
    $is_new = ($status === SAVED_NEW);

    $guest_name = $entity->get('guest_name')->value;
    $room = $entity->getRoom();
    $room_name = $room ? $room->label() : $this->t('Неизвестно');
    $check_in = $entity->getCheckInDate() ? $entity->getCheckInDate()->format('d.m.Y') : '';
    $check_out = $entity->getCheckOutDate() ? $entity->getCheckOutDate()->format('d.m.Y') : '';
    $total = $entity->getTotalPrice();
    $config = \Drupal::config('hotel_reservation.settings');
    $currency = $config->get('currency_symbol') ?: '₽';
    $hotel_name = $config->get('hotel_name') ?: \Drupal::config('system.site')->get('name');

    if ($is_new) {
      $this->messenger()->addStatus($this->t('Бронирование для %guest создано.', ['%guest' => $guest_name]));
    }
    else {
      $this->messenger()->addStatus($this->t('Бронирование для %guest сохранено.', ['%guest' => $guest_name]));
    }

    // --- Send confirmation email if status is confirmed. ---
    if ($entity->get('status')->value === 'confirmed') {
      $this->sendConfirmationEmail($entity, $hotel_name, $room_name, $check_in, $check_out, $total, $currency);
    }

    // --- Send admin notification for new reservations. ---
    if ($is_new && (bool) $config->get('enable_admin_notification')) {
      $this->sendAdminNotification($entity, $hotel_name, $room_name, $check_in, $check_out, $total, $currency);
    }

    $form_state->setRedirect('entity.hr_reservation.collection');
  }

  /**
   * Send a confirmation email to the guest.
   */
  protected function sendConfirmationEmail($entity, $hotel_name, $room_name, $check_in, $check_out, $total, $currency): void {
    $guest_email = $entity->get('guest_email')->value;
    if (empty($guest_email) || !(bool) \Drupal::config('hotel_reservation.settings')->get('enable_guest_confirmation')) {
      return;
    }

    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $params['message'] = $this->t(
      "Уважаемый(ая) @guest,\n\nВаше бронирование в отеле @hotel подтверждено.\n\nНомер: @room\nЗаезд: @check_in\nВыезд: @check_out\nИтого: @total @currency\n\nЖдём вас!\n\nС уважением,\n@hotel",
      [
        '@guest' => $entity->get('guest_name')->value,
        '@hotel' => $hotel_name,
        '@room' => $room_name,
        '@check_in' => $check_in,
        '@check_out' => $check_out,
        '@total' => number_format((float) $total, 2),
        '@currency' => $currency,
      ]
    );

    \Drupal::service('plugin.manager.mail')->mail(
      'hotel_reservation',
      'reservation_confirmation',
      $guest_email,
      $langcode,
      $params
    );
  }

  /**
   * Send an admin notification email about a new reservation.
   */
  protected function sendAdminNotification($entity, $hotel_name, $room_name, $check_in, $check_out, $total, $currency): void {
    $admin_email = \Drupal::config('hotel_reservation.settings')->get('admin_notification_email');
    if (empty($admin_email)) {
      return;
    }

    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $params['guest_name'] = $entity->get('guest_name')->value;
    $params['message'] = $this->t(
      "Создано новое бронирование:\n\nГость: @guest\nEmail: @email\nТелефон: @phone\nНомер: @room\nЗаезд: @check_in\nВыезд: @check_out\nГости: @count\nИтого: @total @currency\n\nСтатус: @status",
      [
        '@guest' => $entity->get('guest_name')->value,
        '@email' => $entity->get('guest_email')->value ?: '—',
        '@phone' => $entity->get('guest_phone')->value,
        '@room' => $room_name,
        '@check_in' => $check_in,
        '@check_out' => $check_out,
        '@count' => $entity->get('guest_count')->value,
        '@total' => number_format((float) $total, 2),
        '@currency' => $currency,
        '@status' => $entity->getStatusLabel(),
      ]
    );

    \Drupal::service('plugin.manager.mail')->mail(
      'hotel_reservation',
      'reservation_admin_notification',
      $admin_email,
      $langcode,
      $params
    );
  }

}
