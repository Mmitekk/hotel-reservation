<?php

namespace Drupal\hotel_reservation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for Hotel Reservation admin pages.
 */
class HotelReservationController extends ControllerBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * Status letter mapping for the calendar.
   */
  protected function getStatusLetter($status) {
    $map = [
      'pending' => 'P',
      'confirmed' => 'C',
      'checked_in' => 'I',
      'checked_out' => 'O',
      'cancelled' => 'X',
      'expired' => 'E',
    ];
    return $map[$status] ?? '?';
  }

  /**
   * Displays the reservation calendar for a given month.
   *
   * @param int|null $month
   *   The month number (1-12). Defaults to current month.
   * @param int|null $year
   *   The year. Defaults to current year.
   *
   * @return array
   *   A render array.
   */
  public function calendar($month = NULL, $year = NULL) {
    $now = new \DateTime();
    $today_str = $now->format('Y-m-d');
    $month = $month !== NULL ? (int) $month : (int) $now->format('n');
    $year = $year !== NULL ? (int) $year : (int) $now->format('Y');

    // Validate month/year.
    if ($month < 1 || $month > 12) {
      $month = (int) $now->format('n');
    }
    if ($year < 2000 || $year > 2100) {
      $year = (int) $now->format('Y');
    }

    $days_in_month = (int) (new \DateTime("{$year}-{$month}-01"))->format('t');
    $month_name = $this->dateFormatter->format(strtotime("{$year}-{$month}-01"), 'custom', 'F');

    // Load all rooms sorted by weight.
    $room_storage = $this->entityTypeManager->getStorage('hr_room');
    $room_ids = $room_storage->getQuery()
      ->sort('sort_weight', 'ASC')
      ->sort('name', 'ASC')
      ->accessCheck(FALSE)
      ->execute();
    $rooms = $room_ids ? $room_storage->loadMultiple($room_ids) : [];

    // Load all reservations overlapping this month.
    $month_start = sprintf('%04d-%02d-01', $year, $month);
    $month_end = sprintf('%04d-%02d-%02d', $year, $month, $days_in_month);
    // We need reservations where check_in < month_end AND check_out > month_start.
    $reservation_storage = $this->entityTypeManager->getStorage('hr_reservation');
    $res_ids = $reservation_storage->getQuery()
      ->condition('check_in', $month_end, '<')
      ->condition('check_out', $month_start, '>')
      ->accessCheck(FALSE)
      ->execute();
    $reservations = $res_ids ? $reservation_storage->loadMultiple($res_ids) : [];

    // Index reservations by room_id.
    $reservations_by_room = [];
    foreach ($reservations as $res) {
      $rid = $res->get('room_id')->target_id;
      $reservations_by_room[$rid][] = $res;
    }

    // Build the days array for the template header.
    $weekday_names = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
    $days = [];
    for ($day = 1; $day <= $days_in_month; $day++) {
      $date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
      $day_dt = new \DateTime($date_str);
      $dow = (int) $day_dt->format('w');
      $days[] = [
        'weekday' => $weekday_names[$dow],
        'number' => $day,
        'date' => $date_str,
        'is_today' => $date_str === $today_str,
        'is_weekend' => ($dow === 0 || $dow === 6),
        'is_past' => $date_str < $today_str,
      ];
    }

    // Build room data matching the template expectations.
    // Template expects: room.label, room.capacity, room.reservations[date][]
    // Each reservation: res.id, res.status, res.guest, res.letter
    $calendar_rooms = [];
    foreach ($rooms as $room) {
      $room_reservations = $reservations_by_room[$room->id()] ?? [];
      $room_data = [
        'label' => $room->label(),
        'capacity' => $room->getCapacity(),
        'reservations' => [],
      ];

      // Index this room's reservations by date.
      foreach ($room_reservations as $res) {
        $ci = $res->get('check_in')->value;
        $co = $res->get('check_out')->value;
        // Walk through each date of the reservation within this month.
        $res_start = max($ci, $month_start);
        $res_end = min($co, $month_end);
        $res_dt = new \DateTime($res_start);
        $end_dt = new \DateTime($res_end);
        while ($res_dt < $end_dt) {
          $date_key = $res_dt->format('Y-m-d');
          $room_data['reservations'][$date_key][] = [
            'id' => $res->id(),
            'status' => $res->get('status')->value,
            'guest' => $res->get('guest_name')->value,
            'letter' => $this->getStatusLetter($res->get('status')->value),
          ];
          $res_dt->modify('+1 day');
        }
      }

      $calendar_rooms[] = $room_data;
    }

    // Build navigation URLs.
    $prev_month = $month - 1;
    $prev_year = $year;
    if ($prev_month < 1) {
      $prev_month = 12;
      $prev_year--;
    }
    $next_month = $month + 1;
    $next_year = $year;
    if ($next_month > 12) {
      $next_month = 1;
      $next_year++;
    }

    $prev_url = Url::fromRoute('hotel_reservation.calendar', ['month' => $prev_month, 'year' => $prev_year])->toString();
    $current_url = Url::fromRoute('hotel_reservation.calendar', ['month' => (int) $now->format('n'), 'year' => (int) $now->format('Y')])->toString();
    $next_url = Url::fromRoute('hotel_reservation.calendar', ['month' => $next_month, 'year' => $next_year])->toString();

    return [
      '#theme' => 'hotel_reservation_admin_calendar',
      '#rooms' => $calendar_rooms,
      '#days' => $days,
      '#month_name' => $month_name,
      '#year' => $year,
      '#prev_url' => $prev_url,
      '#current_url' => $current_url,
      '#next_url' => $next_url,
      '#cache' => [
        'tags' => ['hr_reservation_list', 'hr_room_list'],
        'contexts' => [],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Displays the pricing calendar for a specific room.
   *
   * @param \Drupal\Core\Entity\EntityInterface $hr_room
   *   The room entity.
   * @param int|null $month
   *   The month number (1-12).
   * @param int|null $year
   *   The year.
   *
   * @return array
   *   A render array.
   */
  public function roomPricing(EntityInterface $hr_room, $month = NULL, $year = NULL) {
    $now = new \DateTime();
    $month = $month !== NULL ? (int) $month : (int) $now->format('n');
    $year = $year !== NULL ? (int) $year : (int) $now->format('Y');

    if ($month < 1 || $month > 12) {
      $month = (int) $now->format('n');
    }
    if ($year < 2000 || $year > 2100) {
      $year = (int) $now->format('Y');
    }

    $config = $this->config('hotel_reservation.settings');
    $currency = $config->get('currency_symbol') ?: '₽';
    $base_price = (float) $hr_room->get('base_price')->value;
    $days_in_month = (int) (new \DateTime("{$year}-{$month}-01"))->format('t');
    $month_label = $this->dateFormatter->format(strtotime("{$year}-{$month}-01"), 'custom', 'F Y');

    // Load existing custom pricing for this room and month.
    $month_start = sprintf('%04d-%02d-01', $year, $month);
    $month_end = sprintf('%04d-%02d-%02d', $year, $month, $days_in_month);

    $pricing_storage = $this->entityTypeManager->getStorage('hr_room_pricing');
    $pricing_ids = $pricing_storage->getQuery()
      ->condition('room_id', $hr_room->id())
      ->condition('date', [$month_start, $month_end], 'BETWEEN')
      ->accessCheck(FALSE)
      ->execute();
    $pricings = $pricing_ids ? $pricing_storage->loadMultiple($pricing_ids) : [];

    // Index pricing by date string.
    $custom_prices = [];
    foreach ($pricings as $pricing) {
      $custom_prices[$pricing->get('date')->value] = (float) $pricing->getPrice();
    }

    // Navigation links.
    $prev_month = $month - 1;
    $prev_year = $year;
    if ($prev_month < 1) {
      $prev_month = 12;
      $prev_year--;
    }
    $next_month = $month + 1;
    $next_year = $year;
    if ($next_month > 12) {
      $next_month = 1;
      $next_year++;
    }

    $save_url = Url::fromRoute('hotel_reservation.room_pricing_save', ['hr_room' => $hr_room->id()]);

    // Build a render array using #markup for the form (POST to a separate route).
    $html = '<h2>' . $this->t('Цены @room — @month', [
      '@room' => $hr_room->label(),
      '@month' => $month_label,
    ]) . '</h2>';

    $html .= '<p>' . $this->t('Базовая цена: @price @currency за ночь.', [
      '@price' => number_format($base_price, 2),
      '@currency' => $currency,
    ]) . '</p>';

    // Month navigation.
    $html .= '<div class="pricing-nav">';
    $html .= Link::fromTextAndUrl(
      $this->t('‹ Предыдущий месяц'),
      Url::fromRoute('hotel_reservation.room_pricing', ['hr_room' => $hr_room->id(), 'month' => $prev_month, 'year' => $prev_year])
    )->toString();
    $html .= ' | ';
    $html .= Link::fromTextAndUrl(
      $this->t('Следующий месяц ›'),
      Url::fromRoute('hotel_reservation.room_pricing', ['hr_room' => $hr_room->id(), 'month' => $next_month, 'year' => $next_year])
    )->toString();
    $html .= '</div>';

    // Start the form.
    $html .= '<form method="POST" action="' . $save_url->toString() . '" class="room-pricing-form">';
    $html .= '<input type="hidden" name="month" value="' . $month . '"><input type="hidden" name="year" value="' . $year . '">';

    // Build table.
    $html .= '<table class="room-pricing-table">';
    $html .= '<thead><tr><th>' . $this->t('Дата') . '</th><th>' . $this->t('День') . '</th>';
    $html .= '<th>' . $this->t('Цена (@currency)', ['@currency' => $currency]) . '</th>';
    $html .= '<th>' . $this->t('Разница') . '</th></tr></thead><tbody>';

    for ($day = 1; $day <= $days_in_month; $day++) {
      $date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
      $day_of_week = (new \DateTime($date_str))->format('D');
      $price = isset($custom_prices[$date_str]) ? $custom_prices[$date_str] : $base_price;
      $diff = $price - $base_price;

      $diff_class = 'price-same';
      $diff_text = '—';
      if (abs($diff) > 0.001) {
        if ($diff > 0) {
          $diff_class = 'price-increase';
          $diff_text = '+' . number_format($diff, 2) . ' ' . $currency;
        }
        else {
          $diff_class = 'price-decrease';
          $diff_text = number_format($diff, 2) . ' ' . $currency;
        }
      }

      $row_class = '';
      if (abs($diff) > 0.001) {
        $row_class = ' class="has-custom-price"';
      }

      $html .= '<tr' . $row_class . '>';
      $html .= '<td>' . $date_str . '</td>';
      $html .= '<td>' . $day_of_week . '</td>';
      $html .= '<td><input type="number" name="prices[' . $date_str . ']" value="' . number_format($price, 2, '.', '') . '" step="0.01" min="0" size="10" class="price-input" data-date="' . $date_str . '"></td>';
      $html .= '<td><span class="' . $diff_class . '">' . $diff_text . '</span></td>';
      $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    $html .= '<div class="form-actions"><button type="submit" class="button button--primary">' . $this->t('Сохранить цены') . '</button></div>';
    $html .= '</form>';

    // Back link.
    $html .= '<div class="back-link">' . Link::fromTextAndUrl(
      $this->t('← Назад к номерам'),
      Url::fromRoute('entity.hr_room.collection')
    )->toString() . '</div>';

    return [
      '#markup' => $html,
      '#allowed_tags' => ['h2', 'p', 'div', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'span', 'input', 'button', 'a', 'form', 'strong', 'em'],
      '#attached' => [
        'library' => ['hotel_reservation/room-pricing'],
      ],
      '#cache' => [
        'tags' => ['hr_room:' . $hr_room->id(), 'hr_room_pricing_list'],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Saves room pricing from the pricing calendar form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Core\Entity\EntityInterface $hr_room
   *   The room entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the pricing page.
   */
  public function roomPricingSave(Request $request, EntityInterface $hr_room) {
    $month = (int) $request->request->get('month');
    $year = (int) $request->request->get('year');
    $prices = $request->request->get('prices', []);
    $base_price = (float) $hr_room->get('base_price')->value;

    if (empty($prices)) {
      $this->messenger()->addWarning($this->t('Цены не указаны.'));
    }
    else {
      $pricing_storage = $this->entityTypeManager->getStorage('hr_room_pricing');
      $saved_count = 0;
      $deleted_count = 0;

      // Load existing pricing entries for this room and month.
      $days_in_month = (int) (new \DateTime("{$year}-{$month}-01"))->format('t');
      $month_start = sprintf('%04d-%02d-01', $year, $month);
      $month_end = sprintf('%04d-%02d-%02d', $year, $month, $days_in_month);

      $existing_ids = $pricing_storage->getQuery()
        ->condition('room_id', $hr_room->id())
        ->condition('date', [$month_start, $month_end], 'BETWEEN')
        ->accessCheck(FALSE)
        ->execute();

      $existing_pricings = $existing_ids ? $pricing_storage->loadMultiple($existing_ids) : [];
      $existing_by_date = [];
      foreach ($existing_pricings as $ep) {
        $existing_by_date[$ep->get('date')->value] = $ep;
      }

      foreach ($prices as $date_str => $price_value) {
        // Validate date format.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str)) {
          continue;
        }

        $price = (float) $price_value;

        if (isset($existing_by_date[$date_str])) {
          $pricing_entity = $existing_by_date[$date_str];

          if (abs($price - $base_price) < 0.001) {
            // Price equals base price — delete the custom pricing entry.
            $pricing_entity->delete();
            $deleted_count++;
          }
          else {
            // Update the custom price.
            $pricing_entity->set('price', number_format($price, 2, '.', ''));
            $pricing_entity->save();
            $saved_count++;
          }

          unset($existing_by_date[$date_str]);
        }
        else {
          // Only create a custom pricing entry if different from base.
          if (abs($price - $base_price) >= 0.001) {
            $pricing_entity = $pricing_storage->create([
              'room_id' => $hr_room->id(),
              'date' => $date_str,
              'price' => number_format($price, 2, '.', ''),
            ]);
            $pricing_entity->save();
            $saved_count++;
          }
        }
      }

      if ($saved_count > 0) {
        $this->messenger()->addStatus($this->t('Сохранено @count индивидуальных цен(ы).', ['@count' => $saved_count]));
      }
      if ($deleted_count > 0) {
        $this->messenger()->addStatus($this->t('Удалено @count индивидуальных цен(ы) (возврат к базовой цене).', ['@count' => $deleted_count]));
      }
      if ($saved_count === 0 && $deleted_count === 0) {
        $this->messenger()->addStatus($this->t('Изменений нет. Все цены совпадают с базовой.'));
      }
    }

    $url = Url::fromRoute('hotel_reservation.room_pricing', [
      'hr_room' => $hr_room->id(),
      'month' => $month,
      'year' => $year,
    ]);
    return new RedirectResponse($url->toString());
  }

  /**
   * Changes the status of a reservation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $hr_reservation
   *   The reservation entity.
   * @param string $status
   *   The new status.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If the status transition is invalid.
   */
  public function changeReservationStatus(EntityInterface $hr_reservation, $status) {
    $current_status = $hr_reservation->get('status')->value;

    // Define valid transitions.
    $valid_transitions = [
      'pending' => ['confirmed', 'cancelled', 'expired'],
      'confirmed' => ['checked_in', 'cancelled'],
      'checked_in' => ['checked_out'],
      'checked_out' => [],
      'cancelled' => [],
      'expired' => [],
    ];

    $allowed = $valid_transitions[$current_status] ?? [];

    if (!in_array($status, $allowed)) {
      $this->messenger()->addError($this->t(
        'Нельзя изменить статус бронирования с %from на %to.',
        [
          '%from' => $hr_reservation->getStatusLabel(),
          '%to' => $status,
        ]
      ));
    }
    else {
      $hr_reservation->set('status', $status);
      $hr_reservation->save();

      $this->messenger()->addStatus($this->t(
        'Статус бронирования изменён на %status.',
        ['%status' => $hr_reservation->getStatusLabel()]
      ));

      // Send confirmation email if newly confirmed.
      if ($status === 'confirmed') {
        $this->sendStatusChangeEmail($hr_reservation, 'confirmed');
      }

      // Also notify admin of cancellation.
      if ($status === 'cancelled') {
        $this->sendStatusChangeEmail($hr_reservation, 'cancelled');
      }
    }

    $url = Url::fromRoute('entity.hr_reservation.collection');
    return new RedirectResponse($url->toString());
  }

  /**
   * Send an email on reservation status change.
   *
   * @param \Drupal\hotel_reservation\Entity\Reservation $reservation
   *   The reservation entity.
   * @param string $new_status
   *   The new status.
   */
  protected function sendStatusChangeEmail($reservation, $new_status): void {
    $config = $this->config('hotel_reservation.settings');
    $guest_email = $reservation->get('guest_email')->value;
    $hotel_name = $config->get('hotel_name') ?: $this->config('system.site')->get('name');
    $currency = $config->get('currency_symbol') ?: '₽';

    $room = $reservation->getRoom();
    $room_name = $room ? $room->label() : $this->t('Неизвестно');
    $check_in = $reservation->getCheckInDate() ? $reservation->getCheckInDate()->format('d.m.Y') : '';
    $check_out = $reservation->getCheckOutDate() ? $reservation->getCheckOutDate()->format('d.m.Y') : '';
    $total = number_format((float) $reservation->getTotalPrice(), 2);

    $langcode = $this->languageManager()->getCurrentLanguage()->getId();

    if ($new_status === 'confirmed' && !empty($guest_email) && (bool) $config->get('enable_guest_confirmation')) {
      $params['message'] = $this->t(
        "Уважаемый(ая) @guest,\n\nВаше бронирование в отеле @hotel подтверждено!\n\nНомер: @room\nЗаезд: @check_in\nВыезд: @check_out\nИтого: @total @currency\n\nЖдём вас!\n\nС уважением,\n@hotel",
        [
          '@guest' => $reservation->get('guest_name')->value,
          '@hotel' => $hotel_name,
          '@room' => $room_name,
          '@check_in' => $check_in,
          '@check_out' => $check_out,
          '@total' => $total,
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

    if ($new_status === 'cancelled') {
      $admin_email = $config->get('admin_notification_email');
      if (!empty($admin_email) && (bool) $config->get('enable_admin_notification')) {
        $params['guest_name'] = $reservation->get('guest_name')->value;
        $params['message'] = $this->t(
          "Бронирование отменено:\n\nГость: @guest\nНомер: @room\nЗаезд: @check_in\nВыезд: @check_out\n\nСтатус: Отменено",
          [
            '@guest' => $reservation->get('guest_name')->value,
            '@room' => $room_name,
            '@check_in' => $check_in,
            '@check_out' => $check_out,
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
  }

}
