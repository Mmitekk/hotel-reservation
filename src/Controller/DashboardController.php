<?php

namespace Drupal\hotel_reservation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the hotel reservation admin dashboard and CSV export.
 */
class DashboardController extends ControllerBase {

  /**
   * Builds the admin dashboard page.
   *
   * @return array
   *   A render array for the dashboard page.
   */
  public function dashboard() {
    $config = \Drupal::config('hotel_reservation.settings');
    $currency = $config->get('currency_symbol') ?: '₽';
    $reservation_storage = \Drupal::entityTypeManager()->getStorage('hr_reservation');
    $room_storage = \Drupal::entityTypeManager()->getStorage('hr_room');

    // --- Count rooms ---
    $total_rooms_query = $room_storage->getQuery()->accessCheck(FALSE);
    $total_rooms = (int) $total_rooms_query->count()->execute();

    $published_rooms_query = $room_storage->getQuery()
      ->condition('status', 1)
      ->accessCheck(FALSE);
    $published_rooms = (int) $published_rooms_query->count()->execute();

    // --- Count reservations by status ---
    $total_reservations_query = $reservation_storage->getQuery()->accessCheck(FALSE);
    $total_reservations = (int) $total_reservations_query->count()->execute();

    $pending_reservations_query = $reservation_storage->getQuery()
      ->condition('status', 'pending')
      ->accessCheck(FALSE);
    $pending_reservations = (int) $pending_reservations_query->count()->execute();

    $confirmed_reservations_query = $reservation_storage->getQuery()
      ->condition('status', 'confirmed')
      ->accessCheck(FALSE);
    $confirmed_reservations = (int) $confirmed_reservations_query->count()->execute();

    $checked_in_reservations_query = $reservation_storage->getQuery()
      ->condition('status', 'checked_in')
      ->accessCheck(FALSE);
    $checked_in_reservations = (int) $checked_in_reservations_query->count()->execute();

    // --- Calculate total revenue (confirmed + checked_in) ---
    $revenue_query = $reservation_storage->getQuery()
      ->condition('status', ['confirmed', 'checked_in'], 'IN')
      ->accessCheck(FALSE);
    $revenue_ids = $revenue_query->execute();
    $total_revenue = 0.0;
    if (!empty($revenue_ids)) {
      $revenue_reservations = $reservation_storage->loadMultiple($revenue_ids);
      foreach ($revenue_reservations as $rev) {
        $total_revenue += (float) $rev->get('total_price')->value;
      }
    }

    // --- Calculate average occupancy rate ---
    $avg_occupancy = 0.0;
    if ($published_rooms > 0) {
      $today_dt = new \DateTime('today');
      $today_str = $today_dt->format('Y-m-d');
      // Count rooms that are occupied today (have active reservations covering today).
      $occupied_query = $reservation_storage->getQuery()
        ->condition('status', ['confirmed', 'checked_in'], 'IN')
        ->condition('check_in', $today_str, '<=')
        ->condition('check_out', $today_str, '>')
        ->accessCheck(FALSE);
      $occupied_ids = $occupied_query->execute();
      // Count distinct rooms from occupied reservations.
      $occupied_room_ids = [];
      if (!empty($occupied_ids)) {
        $occupied_reservations = $reservation_storage->loadMultiple($occupied_ids);
        foreach ($occupied_reservations as $occ_res) {
          $occupied_room_ids[] = $occ_res->get('room_id')->target_id;
        }
        $occupied_room_ids = array_unique($occupied_room_ids);
      }
      $avg_occupancy = round((count($occupied_room_ids) / $published_rooms) * 100, 1);
    }

    // --- Recent pending reservations (last 10) ---
    $pending_list_query = $reservation_storage->getQuery()
      ->condition('status', 'pending')
      ->sort('created', 'DESC')
      ->range(0, 10)
      ->accessCheck(FALSE);
    $pending_ids = $pending_list_query->execute();
    $pending_reservations_list = [];
    if (!empty($pending_ids)) {
      $pending_entities = $reservation_storage->loadMultiple($pending_ids);
      foreach ($pending_entities as $reservation) {
        $room_entity = $reservation->get('room_id')->entity;
        $room_name = $room_entity ? $room_entity->label() : $this->t('—');
        $check_in_value = $reservation->get('check_in')->value;
        $check_in_formatted = '';
        if ($check_in_value) {
          $check_in_dt = new \DateTime($check_in_value);
          $check_in_formatted = $check_in_dt->format('d.m.Y');
        }
        $check_out_value = $reservation->get('check_out')->value;
        $check_out_formatted = '';
        if ($check_out_value) {
          $check_out_dt = new \DateTime($check_out_value);
          $check_out_formatted = $check_out_dt->format('d.m.Y');
        }
        $total_price = number_format((float) $reservation->get('total_price')->value, 2, '.', ' ');
        $created_time = (int) $reservation->get('created')->value;
        $created_formatted = \Drupal::service('date.formatter')->format($created_time, 'short');

        $confirm_url = Url::fromRoute('hotel_reservation.reservation_status', [
          'hr_reservation' => $reservation->id(),
          'status' => 'confirmed',
        ]);
        $cancel_url = Url::fromRoute('hotel_reservation.reservation_status', [
          'hr_reservation' => $reservation->id(),
          'status' => 'cancelled',
        ]);

        $pending_reservations_list[] = [
          'id' => $reservation->id(),
          'guest_name' => $reservation->get('guest_name')->value,
          'room_name' => $room_name,
          'check_in' => $check_in_formatted,
          'check_out' => $check_out_formatted,
          'total_price' => $total_price,
          'created' => $created_formatted,
          'confirm_url' => $confirm_url->toString(),
          'cancel_url' => $cancel_url->toString(),
        ];
      }
    }

    // --- Upcoming check-ins (next 3 days) ---
    $today_dt = new \DateTime('today');
    $three_days_str = (clone $today_dt)->modify('+3 days')->format('Y-m-d');
    $today_str = $today_dt->format('Y-m-d');

    $upcoming_query = $reservation_storage->getQuery()
      ->condition('status', ['confirmed'], 'IN')
      ->condition('check_in', $today_str, '>=')
      ->condition('check_in', $three_days_str, '<=')
      ->sort('check_in', 'ASC')
      ->accessCheck(FALSE);
    $upcoming_ids = $upcoming_query->execute();
    $upcoming_checkins = [];
    if (!empty($upcoming_ids)) {
      $upcoming_entities = $reservation_storage->loadMultiple($upcoming_ids);
      foreach ($upcoming_entities as $up_res) {
        $up_room = $up_res->get('room_id')->entity;
        $up_room_name = $up_room ? $up_room->label() : $this->t('—');
        $up_check_in_value = $up_res->get('check_in')->value;
        $up_check_in_formatted = '';
        if ($up_check_in_value) {
          $up_check_in_dt = new \DateTime($up_check_in_value);
          $up_check_in_formatted = $up_check_in_dt->format('d.m.Y');
        }
        $upcoming_checkins[] = [
          'id' => $up_res->id(),
          'guest_name' => $up_res->get('guest_name')->value,
          'room_name' => $up_room_name,
          'check_in' => $up_check_in_formatted,
        ];
      }
    }

    $stats = [
      'total_rooms' => $total_rooms,
      'published_rooms' => $published_rooms,
      'total_reservations' => $total_reservations,
      'pending_reservations' => $pending_reservations,
      'confirmed_reservations' => $confirmed_reservations,
      'checked_in_reservations' => $checked_in_reservations,
      'total_revenue' => number_format($total_revenue, 2, '.', ' '),
      'avg_occupancy' => $avg_occupancy,
    ];

    // --- Weekly revenue (last 7 days) ---
    $weekly_revenue = [];
    $weekly_total = 0.0;
    $max_daily = 0.0;
    $day_of_week_map = [
      0 => ['day' => 'Monday', 'day_short' => 'Пн'],
      1 => ['day' => 'Tuesday', 'day_short' => 'Вт'],
      2 => ['day' => 'Wednesday', 'day_short' => 'Ср'],
      3 => ['day' => 'Thursday', 'day_short' => 'Чт'],
      4 => ['day' => 'Friday', 'day_short' => 'Пт'],
      5 => ['day' => 'Saturday', 'day_short' => 'Сб'],
      6 => ['day' => 'Sunday', 'day_short' => 'Вс'],
    ];
    for ($d = 6; $d >= 0; $d--) {
      $day_dt = (clone $today_dt)->modify('-' . $d . ' days');
      $day_str = $day_dt->format('Y-m-d');
      $next_day_str = (clone $day_dt)->modify('+1 day')->format('Y-m-d');

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
          // Allocate a proportional share of total revenue to this day.
          $day_total += $total / $nights;
        }
      }

      if ($day_total > $max_daily) {
        $max_daily = $day_total;
      }

      $weekly_revenue[] = [
        'day' => $day_of_week_map[$day_dt->format('w')]['day'],
        'day_short' => $day_of_week_map[$day_dt->format('w')]['day_short'],
        'amount' => $day_total,
        'formatted_amount' => number_format($day_total, 0, '.', ' ') . ' ' . $currency,
        'height_pct' => $max_daily > 0 ? max(5, round(($day_total / $max_daily) * 100)) : 5,
        'is_today' => $d === 0,
      ];
      $weekly_total += $day_total;
    }
    // Reverse to chronological order.
    $weekly_revenue = array_reverse($weekly_revenue);

    $export_url = Url::fromRoute('hotel_reservation.export_csv')->toString();

    $build = [
      '#theme' => 'hotel_reservation_dashboard',
      '#stats' => $stats,
      '#pending_reservations' => $pending_reservations_list,
      '#upcoming_checkins' => $upcoming_checkins,
      '#currency' => $currency,
      '#weekly_revenue' => $weekly_revenue,
      '#weekly_total' => number_format($weekly_total, 2, '.', ' ') . ' ' . $currency,
      '#export_url' => $export_url,
      '#attached' => [
        'library' => [
          'hotel_reservation/admin-styles',
          'hotel_reservation/dashboard',
        ],
      ],
      '#cache' => [
        'tags' => ['hr_reservation_list', 'hr_room_list'],
        'max-age' => 0,
      ],
    ];

    return $build;
  }

  /**
   * Exports reservations to CSV as a streamed response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request (for filter query parameters).
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   A streamed CSV file download response.
   */
  public function exportCsv(Request $request) {
    $status = $request->query->get('status', '');
    $date_from = $request->query->get('date_from', '');
    $date_to = $request->query->get('date_to', '');
    $room = $request->query->get('room', '');

    $reservation_storage = \Drupal::entityTypeManager()->getStorage('hr_reservation');

    // Build the query with the same filters as ReservationListBuilder.
    $query = $reservation_storage->getQuery()->accessCheck(FALSE);

    if ($status !== '') {
      $query->condition('status', $status);
    }
    if ($date_from !== '') {
      $query->condition('check_in', $date_from, '>=');
    }
    if ($date_to !== '') {
      $query->condition('check_out', $date_to, '<=');
    }
    if ($room !== '') {
      $query->condition('room_id', (int) $room);
    }

    $query->sort('created', 'DESC');
    $ids = $query->execute();

    $filename = 'reservations_' . date('Y-m-d') . '.csv';

    $response = new StreamedResponse(function () use ($ids) {
      $handle = fopen('php://output', 'w');
      if ($handle === FALSE) {
        return;
      }

      // BOM for Excel UTF-8 compatibility.
      fwrite($handle, "\xEF\xBB\xBF");

      // Russian column headers.
      $headers = [
        'ID',
        'Гость',
        'Телефон',
        'Email',
        'Номер',
        'Заезд',
        'Выезд',
        'Статус',
        'Гости',
        'Итого',
        'Создано',
        'Заметки админа',
      ];
      fputcsv($handle, $headers, ';');

      if (!empty($ids)) {
        $reservations = \Drupal::entityTypeManager()->getStorage('hr_reservation')->loadMultiple($ids);
        $status_options = \Drupal\hotel_reservation\Entity\Reservation::getStatusOptions();
        foreach ($reservations as $reservation) {
          $room_entity = $reservation->get('room_id')->entity;
          $room_label = $room_entity ? $room_entity->label() : '';

          $check_in_val = $reservation->get('check_in')->value;
          $check_in_fmt = '';
          if ($check_in_val) {
            $ci = new \DateTime($check_in_val);
            $check_in_fmt = $ci->format('d.m.Y');
          }

          $check_out_val = $reservation->get('check_out')->value;
          $check_out_fmt = '';
          if ($check_out_val) {
            $co = new \DateTime($check_out_val);
            $check_out_fmt = $co->format('d.m.Y');
          }

          $status_val = $reservation->get('status')->value;
          $status_label = $status_options[$status_val] ?? $status_val;

          $created_ts = (int) $reservation->get('created')->value;
          $created_fmt = date('d.m.Y H:i', $created_ts);

          $admin_notes_value = '';
          $admin_notes_field = $reservation->get('admin_notes')->value;
          if ($admin_notes_field) {
            // Strip HTML tags and collapse whitespace.
            $admin_notes_value = trim(preg_replace('/\s+/', ' ', strip_tags($admin_notes_field)));
          }

          $row = [
            $reservation->id(),
            $reservation->get('guest_name')->value,
            $reservation->get('guest_phone')->value,
            $reservation->get('guest_email')->value,
            $room_label,
            $check_in_fmt,
            $check_out_fmt,
            $status_label,
            $reservation->get('guest_count')->value,
            number_format((float) $reservation->get('total_price')->value, 2, '.', ''),
            $created_fmt,
            $admin_notes_value,
          ];
          fputcsv($handle, $row, ';');
        }
      }

      fclose($handle);
    });

    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

    return $response;
  }

}
