<?php

namespace Drupal\hotel_reservation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\hotel_reservation\Entity\Reservation;

/**
 * Controller for the Booking Analytics admin page.
 */
class AnalyticsController extends ControllerBase {

  /**
   * Room type labels in Russian.
   *
   * @var array
   */
  protected $roomTypeOptions = [
    'standard' => 'Стандарт',
    'superior' => 'Супериор',
    'deluxe' => 'Делюкс',
    'suite' => 'Сьют',
    'apartment' => 'Апартаменты',
    'villa' => 'Вилла',
    'family' => 'Семейный',
    'economy' => 'Эконом',
  ];

  /**
   * Status colors for the analytics chart.
   *
   * @var array
   */
  protected $statusColors = [
    'pending' => '#f59e0b',
    'confirmed' => '#10b981',
    'checked_in' => '#0ea5e9',
    'checked_out' => '#64748b',
    'cancelled' => '#f43f5e',
    'expired' => '#a8a29e',
  ];

  /**
   * Builds the Booking Analytics page.
   *
   * @return array
   *   A render array for the analytics page.
   */
  public function analytics() {
    $config = \Drupal::config('hotel_reservation.settings');
    $currency = $config->get('currency_symbol') ?: '₽';
    $reservation_storage = \Drupal::entityTypeManager()->getStorage('hr_reservation');
    $room_storage = \Drupal::entityTypeManager()->getStorage('hr_room');

    // --- Total reservations ---
    $total_query = $reservation_storage->getQuery()->accessCheck(FALSE);
    $total_reservations = (int) $total_query->count()->execute();

    // --- Status distribution ---
    $status_options = Reservation::getStatusOptions();
    $all_statuses = ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'expired'];
    $status_distribution = [];
    foreach ($all_statuses as $status_key) {
      $count_query = $reservation_storage->getQuery()
        ->condition('status', $status_key)
        ->accessCheck(FALSE);
      $count = (int) $count_query->count()->execute();
      $status_distribution[] = [
        'key' => $status_key,
        'label' => $status_options[$status_key] ?? $status_key,
        'count' => $count,
        'color' => $this->statusColors[$status_key] ?? '#9ca3af',
      ];
    }

    // --- Revenue reservations (confirmed + checked_in) ---
    $revenue_query = $reservation_storage->getQuery()
      ->condition('status', ['confirmed', 'checked_in'], 'IN')
      ->accessCheck(FALSE);
    $revenue_ids = $revenue_query->execute();
    $total_revenue = 0.0;
    $revenue_count = 0;
    $revenue_reservations = [];

    if (!empty($revenue_ids)) {
      $revenue_reservations = $reservation_storage->loadMultiple($revenue_ids);
      $revenue_count = count($revenue_reservations);
      foreach ($revenue_reservations as $rev) {
        $total_revenue += (float) $rev->get('total_price')->value;
      }
    }

    // --- Conversion rate ---
    $conversion_rate = $total_reservations > 0
      ? round(($revenue_count / $total_reservations) * 100, 1)
      : 0.0;

    // --- Average booking value ---
    $avg_booking_value = $revenue_count > 0
      ? round($total_revenue / $revenue_count, 2)
      : 0.0;

    // --- Average stay (all reservations with valid dates) ---
    $avg_stay = 0.0;
    $all_ids = $reservation_storage->getQuery()->accessCheck(FALSE)->execute();
    if (!empty($all_ids)) {
      $all_reservations = $reservation_storage->loadMultiple($all_ids);
      $total_nights = 0;
      $valid_count = 0;
      foreach ($all_reservations as $res) {
        $ci = $res->get('check_in')->value;
        $co = $res->get('check_out')->value;
        if ($ci && $co) {
          $nights = (new \DateTime($co))->diff(new \DateTime($ci))->days;
          if ($nights > 0) {
            $total_nights += $nights;
            $valid_count++;
          }
        }
      }
      if ($valid_count > 0) {
        $avg_stay = round($total_nights / $valid_count, 1);
      }
    }

    // --- Room revenue ranking ---
    $rooms = $room_storage->loadMultiple();
    $room_revenue_data = [];
    foreach ($rooms as $room) {
      $room_rev = 0.0;
      $room_bookings = 0;
      foreach ($revenue_reservations as $rev) {
        if ($rev->get('room_id')->target_id === $room->id()) {
          $room_rev += (float) $rev->get('total_price')->value;
          $room_bookings++;
        }
      }
      $room_type = $room->get('room_type')->value;
      $room_revenue_data[] = [
        'room_id' => $room->id(),
        'name' => $room->label(),
        'room_type' => $this->roomTypeOptions[$room_type] ?? $room_type,
        'revenue' => $room_rev,
        'formatted_revenue' => number_format($room_rev, 0, '.', ' ') . ' ' . $currency,
        'bookings' => $room_bookings,
      ];
    }
    // Sort by revenue descending.
    usort($room_revenue_data, function ($a, $b) {
      return $b['revenue'] <=> $a['revenue'];
    });
    // Calculate bar widths.
    $max_room_revenue = 0.0;
    foreach ($room_revenue_data as $rd) {
      if ($rd['revenue'] > $max_room_revenue) {
        $max_room_revenue = $rd['revenue'];
      }
    }
    foreach ($room_revenue_data as &$rd) {
      $rd['width_pct'] = $max_room_revenue > 0
        ? max(3, round(($rd['revenue'] / $max_room_revenue) * 100))
        : 3;
    }
    unset($rd);

    // --- Top room ---
    $top_room = NULL;
    if (!empty($room_revenue_data)) {
      $top = $room_revenue_data[0];
      $top_room = [
        'name' => $top['name'],
        'room_type' => $top['room_type'],
        'revenue' => $top['formatted_revenue'],
        'bookings' => $top['bookings'],
      ];
    }

    // --- Status distribution bar widths ---
    $max_status_count = 0;
    foreach ($status_distribution as $sd) {
      if ($sd['count'] > $max_status_count) {
        $max_status_count = $sd['count'];
      }
    }
    foreach ($status_distribution as &$sd) {
      $sd['width_pct'] = $max_status_count > 0
        ? max(3, round(($sd['count'] / $max_status_count) * 100))
        : 3;
    }
    unset($sd);

    // --- Weekly data (last 7 days) ---
    $today_dt = new \DateTime('today');
    $day_of_week_map = [
      0 => ['day' => 'Sunday', 'day_short' => 'Вс'],
      1 => ['day' => 'Monday', 'day_short' => 'Пн'],
      2 => ['day' => 'Tuesday', 'day_short' => 'Вт'],
      3 => ['day' => 'Wednesday', 'day_short' => 'Ср'],
      4 => ['day' => 'Thursday', 'day_short' => 'Чт'],
      5 => ['day' => 'Friday', 'day_short' => 'Пт'],
      6 => ['day' => 'Saturday', 'day_short' => 'Сб'],
    ];
    $weekly_data = [];
    $max_weekly_revenue = 0.0;
    $max_weekly_bookings = 0;

    for ($d = 6; $d >= 0; $d--) {
      $day_dt = (clone $today_dt)->modify('-' . $d . ' days');
      $day_str = $day_dt->format('Y-m-d');
      $next_day_str = (clone $day_dt)->modify('+1 day')->format('Y-m-d');

      // Revenue: proportional allocation from confirmed+checked_in reservations active on this day.
      $day_rev_query = $reservation_storage->getQuery()
        ->condition('status', ['confirmed', 'checked_in'], 'IN')
        ->condition('check_in', $next_day_str, '<')
        ->condition('check_out', $day_str, '>=')
        ->accessCheck(FALSE);
      $day_rev_ids = $day_rev_query->execute();
      $day_total = 0.0;
      if (!empty($day_rev_ids)) {
        $day_res = $reservation_storage->loadMultiple($day_rev_ids);
        foreach ($day_res as $day_rev) {
          $total = (float) $day_rev->get('total_price')->value;
          $ci = $day_rev->get('check_in')->value;
          $co = $day_rev->get('check_out')->value;
          $nights = 1;
          if ($ci && $co) {
            $nights = (new \DateTime($co))->diff(new \DateTime($ci))->days;
            if ($nights < 1) {
              $nights = 1;
            }
          }
          $day_total += $total / $nights;
        }
      }

      // Bookings count: reservations active on that day (any non-cancelled/expired).
      $day_book_query = $reservation_storage->getQuery()
        ->condition('status', ['pending', 'confirmed', 'checked_in', 'checked_out'], 'IN')
        ->condition('check_in', $next_day_str, '<')
        ->condition('check_out', $day_str, '>=')
        ->accessCheck(FALSE);
      $day_bookings = (int) $day_book_query->count()->execute();

      if ($day_total > $max_weekly_revenue) {
        $max_weekly_revenue = $day_total;
      }
      if ($day_bookings > $max_weekly_bookings) {
        $max_weekly_bookings = $day_bookings;
      }

      $weekly_data[] = [
        'date' => $day_dt->format('d.m'),
        'day_short' => $day_of_week_map[$day_dt->format('w')]['day_short'],
        'revenue' => $day_total,
        'formatted_revenue' => number_format($day_total, 0, '.', ' ') . ' ' . $currency,
        'bookings' => $day_bookings,
        'is_today' => $d === 0,
      ];
    }
    // Already in chronological order (d=6..0 iterates past to present).

    // Calculate weekly bar widths.
    foreach ($weekly_data as &$wd) {
      $wd['revenue_height'] = $max_weekly_revenue > 0
        ? max(5, round(($wd['revenue'] / $max_weekly_revenue) * 100))
        : 5;
      $wd['bookings_height'] = $max_weekly_bookings > 0
        ? max(5, round(($wd['bookings'] / $max_weekly_bookings) * 100))
        : 5;
    }
    unset($wd);

    // --- Monthly trend (last 12 months) ---
    $month_names = [
      1 => 'Январь', 2 => 'Февраль', 3 => 'Март',
      4 => 'Апрель', 5 => 'Май', 6 => 'Июнь',
      7 => 'Июль', 8 => 'Август', 9 => 'Сентябрь',
      10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
    ];
    $monthly_data = [];
    for ($m = 11; $m >= 0; $m--) {
      $month_dt = (clone $today_dt)->modify('first day of this month')->modify('-' . $m . ' months');
      $month_start = $month_dt->format('Y-m-01');
      $month_end = (clone $month_dt)->modify('last day of this month')->format('Y-m-d');
      $month_end_plus = (clone $month_dt)->modify('last day of this month')->modify('+1 day')->format('Y-m-d');
      $year_month = $month_dt->format('Y-m');
      $month_name = $month_names[(int) $month_dt->format('n')];

      // Revenue: reservations that overlap with this month (confirmed+checked_in).
      $m_rev_query = $reservation_storage->getQuery()
        ->condition('status', ['confirmed', 'checked_in'], 'IN')
        ->condition('check_in', $month_end_plus, '<')
        ->condition('check_out', $month_start, '>')
        ->accessCheck(FALSE);
      $m_rev_ids = $m_rev_query->execute();
      $m_total = 0.0;
      if (!empty($m_rev_ids)) {
        $m_res = $reservation_storage->loadMultiple($m_rev_ids);
        foreach ($m_res as $m_rev) {
          $total = (float) $m_rev->get('total_price')->value;
          $ci = $m_rev->get('check_in')->value;
          $co = $m_rev->get('check_out')->value;
          $nights = 1;
          if ($ci && $co) {
            $nights = (new \DateTime($co))->diff(new \DateTime($ci))->days;
            if ($nights < 1) {
              $nights = 1;
            }
          }
          // Calculate how many nights of this reservation fall within this month.
          $res_start = new \DateTime($ci);
          $res_end = new \DateTime($co);
          $overlap_start = max($res_start, new \DateTime($month_start));
          $overlap_end = min($res_end, new \DateTime($month_end_plus));
          $overlap_nights = $overlap_start->diff($overlap_end)->days;
          if ($overlap_nights < 0) {
            $overlap_nights = 0;
          }
          $m_total += ($total / $nights) * $overlap_nights;
        }
      }

      // Bookings: reservations created or active in this month (any status except cancelled/expired).
      $m_book_query = $reservation_storage->getQuery()
        ->condition('status', ['pending', 'confirmed', 'checked_in', 'checked_out'], 'IN')
        ->condition('check_in', $month_end_plus, '<')
        ->condition('check_out', $month_start, '>')
        ->accessCheck(FALSE);
      $m_bookings = (int) $m_book_query->count()->execute();

      $monthly_data[] = [
        'month_name' => $month_name,
        'year_month' => $year_month,
        'revenue' => $m_total,
        'formatted_revenue' => number_format($m_total, 0, '.', ' ') . ' ' . $currency,
        'bookings' => $m_bookings,
      ];
    }

    // --- KPI stats ---
    $stats = [
      'total_reservations' => $total_reservations,
      'conversion_rate' => $conversion_rate,
      'avg_booking_value' => number_format($avg_booking_value, 0, '.', ' ') . ' ' . $currency,
      'avg_stay' => $avg_stay,
    ];

    return [
      '#theme' => 'hotel_reservation_analytics',
      '#stats' => $stats,
      '#status_distribution' => $status_distribution,
      '#room_revenue' => $room_revenue_data,
      '#top_room' => $top_room,
      '#avg_stay' => (string) $avg_stay,
      '#weekly_data' => $weekly_data,
      '#monthly_data' => $monthly_data,
      '#currency' => $currency,
      '#conversion_rate' => $conversion_rate,
      '#avg_booking_value' => $avg_booking_value,
      '#attached' => [
        'library' => [
          'hotel_reservation/analytics',
        ],
      ],
      '#cache' => [
        'tags' => ['hr_reservation_list', 'hr_room_list'],
        'max-age' => 0,
      ],
    ];
  }

}
