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
    $month_label = $this->dateFormatter->format(strtotime("{$year}-{$month}-01"), 'custom', 'F Y');

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

    // Build calendar data.
    $calendar_rooms = [];
    foreach ($rooms as $room) {
      $room_data = [
        'id' => $room->id(),
        'name' => $room->label(),
        'capacity' => $room->getCapacity(),
        'days' => [],
      ];

      $room_reservations = $reservations_by_room[$room->id()] ?? [];

      for ($day = 1; $day <= $days_in_month; $day++) {
        $date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $day_data = [
          'date' => $date_str,
          'day' => $day,
          'reservations' => [],
        ];

        foreach ($room_reservations as $res) {
          $ci = $res->get('check_in')->value;
          $co = $res->get('check_out')->value;
          // Check if this date falls within the reservation (check_in <= date < check_out).
          if ($date_str >= $ci && $date_str < $co) {
            $day_data['reservations'][] = [
              'id' => $res->id(),
              'guest' => $res->get('guest_name')->value,
              'status' => $res->get('status')->value,
            ];
          }
        }

        $room_data['days'][] = $day_data;
      }

      $calendar_rooms[] = $room_data;
    }

    // Build month navigation links.
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

    $prev_url = Url::fromRoute('hotel_reservation.calendar', ['month' => $prev_month, 'year' => $prev_year]);
    $next_url = Url::fromRoute('hotel_reservation.calendar', ['month' => $next_month, 'year' => $next_year]);

    return [
      '#theme' => 'hotel_reservation_admin_calendar',
      '#rooms' => $calendar_rooms,
      '#reservations' => $reservations,
      '#month' => $month,
      '#year' => $year,
      '#attached' => [
        'library' => [
          'hotel_reservation/admin-calendar',
        ],
        'drupalSettings' => [
          'hotelReservation' => [
            'monthLabel' => $month_label,
            'daysInMonth' => $days_in_month,
            'prevUrl' => $prev_url->toString(),
            'nextUrl' => $next_url->toString(),
          ],
        ],
      ],
      '#cache' => [
        'tags' => ['hr_reservation_list', 'hr_room_list'],
        'contexts' => [],
        'max-age' => 0,
      ],
      'nav' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['calendar-nav']],
        'prev' => Link::fromTextAndUrl($this->t('‹ Previous'), $prev_url)->toRenderable(),
        'current' => ['#markup' => '<strong>' . $month_label . '</strong>'],
        'next' => Link::fromTextAndUrl($this->t('Next ›'), $next_url)->toRenderable(),
      ],
      'legend' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['calendar-legend']],
        '#markup' => '<span class="legend-item"><span class="legend-color legend-pending"></span> P = Pending</span>'
          . '<span class="legend-item"><span class="legend-color legend-confirmed"></span> C = Confirmed</span>'
          . '<span class="legend-item"><span class="legend-color legend-blank"></span> Blank = Available</span>',
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
    $html = '<h2>' . $this->t('Pricing for @room — @month', [
      '@room' => $hr_room->label(),
      '@month' => $month_label,
    ]) . '</h2>';

    $html .= '<p>' . $this->t('Base price: @price @currency per night.', [
      '@price' => number_format($base_price, 2),
      '@currency' => $currency,
    ]) . '</p>';

    // Month navigation.
    $html .= '<div class="pricing-nav">';
    $html .= Link::fromTextAndUrl(
      $this->t('‹ Previous Month'),
      Url::fromRoute('hotel_reservation.room_pricing', ['hr_room' => $hr_room->id(), 'month' => $prev_month, 'year' => $prev_year])
    )->toString();
    $html .= ' | ';
    $html .= Link::fromTextAndUrl(
      $this->t('Next Month ›'),
      Url::fromRoute('hotel_reservation.room_pricing', ['hr_room' => $hr_room->id(), 'month' => $next_month, 'year' => $next_year])
    )->toString();
    $html .= '</div>';

    // Start the form.
    $html .= '<form method="POST" action="' . $save_url->toString() . '" class="room-pricing-form">';
    $html .= '<input type="hidden" name="month" value="' . $month . '"><input type="hidden" name="year" value="' . $year . '">';

    // Build table.
    $html .= '<table class="room-pricing-table">';
    $html .= '<thead><tr><th>' . $this->t('Date') . '</th><th>' . $this->t('Day') . '</th>';
    $html .= '<th>' . $this->t('Price (@currency)', ['@currency' => $currency]) . '</th>';
    $html .= '<th>' . $this->t('Difference') . '</th></tr></thead><tbody>';

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
    $html .= '<div class="form-actions"><button type="submit" class="button button--primary">' . $this->t('Save Prices') . '</button></div>';
    $html .= '</form>';

    // Back link.
    $html .= '<div class="back-link">' . Link::fromTextAndUrl(
      $this->t('← Back to rooms'),
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
      $this->messenger()->addWarning($this->t('No prices submitted.'));
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
        $this->messenger()->addStatus($this->t('Saved @count custom price(s).', ['@count' => $saved_count]));
      }
      if ($deleted_count > 0) {
        $this->messenger()->addStatus($this->t('Removed @count custom price(s) (reverted to base price).', ['@count' => $deleted_count]));
      }
      if ($saved_count === 0 && $deleted_count === 0) {
        $this->messenger()->addStatus($this->t('No changes detected. All prices match the base price.'));
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
        'Cannot change reservation status from %from to %to.',
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
        'Reservation status changed to %status.',
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
    $room_name = $room ? $room->label() : $this->t('Unknown');
    $check_in = $reservation->getCheckInDate() ? $reservation->getCheckInDate()->format('d.m.Y') : '';
    $check_out = $reservation->getCheckOutDate() ? $reservation->getCheckOutDate()->format('d.m.Y') : '';
    $total = number_format((float) $reservation->getTotalPrice(), 2);

    $langcode = $this->languageManager()->getCurrentLanguage()->getId();

    if ($new_status === 'confirmed' && !empty($guest_email) && (bool) $config->get('enable_guest_confirmation')) {
      $params['message'] = $this->t(
        "Dear @guest,\n\nYour reservation at @hotel has been confirmed!\n\nRoom: @room\nCheck-in: @check_in\nCheck-out: @check_out\nTotal: @total @currency\n\nWe look forward to your stay!\n\nBest regards,\n@hotel",
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

      $this->mailManager()->mail(
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
          "A reservation has been cancelled:\n\nGuest: @guest\nRoom: @room\nCheck-in: @check_in\nCheck-out: @check_out\n\nStatus: Cancelled",
          [
            '@guest' => $reservation->get('guest_name')->value,
            '@room' => $room_name,
            '@check_in' => $check_in,
            '@check_out' => $check_out,
          ]
        );

        $this->mailManager()->mail(
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
