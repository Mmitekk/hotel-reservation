<?php

namespace Drupal\hotel_reservation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\hotel_reservation\Entity\Reservation;

/**
 * Guest's own bookings page («Мои бронирования»).
 */
class MyBookingsController extends ControllerBase {

  /**
   * Builds the current user's bookings page.
   *
   * @return array
   *   A render array.
   */
  public function myBookings() {
    $account = $this->currentUser();
    $config = $this->config('hotel_reservation.settings');
    $currency = $config->get('currency_symbol') ?: '₽';
    $check_in_time = $config->get('check_in_time') ?: '14:00';
    $check_out_time = $config->get('check_out_time') ?: '12:00';

    $bookings = [];
    $email = '';
    if (!$account->isAnonymous()) {
      $email = trim((string) $account->getEmail());
    }

    $storage = $this->entityTypeManager()->getStorage('hr_reservation');
    // Own bookings: linked by account id (set when the booking auto-created
    // the account) or by matching guest email. The uid column exists only
    // after update 10009, hence the guard.
    $query = $storage->getQuery()->accessCheck(FALSE);
    if (hotel_reservation_reservation_has_uid()) {
      $or = $query->orConditionGroup()
        ->condition('uid', (int) $account->id());
      if ($email !== '') {
        $or->condition('guest_email', $email);
      }
      $ids = $query
        ->condition($or)
        ->execute();
    }
    elseif ($email !== '') {
      $ids = $query
        ->condition('guest_email', $email)
        ->execute();
    }
    else {
      $ids = [];
    }

    // Phone fallback: same person, different account (or a booking made
    // before the uid link existed). Phone formats differ
    // ("+7 (999) 123-45-67" vs "79991234567"), so normalize in PHP.
    $account_digits = preg_replace('/\D/', '', (string) $account->getAccountName());
    if (strlen($account_digits) >= 10) {
      if (strlen($account_digits) === 11 && $account_digits[0] === '8') {
        $account_digits = '7' . substr($account_digits, 1);
      }
      try {
        $all_ids = $storage->getQuery()->accessCheck(FALSE)->execute();
        $rest = array_values(array_diff(array_values($all_ids), array_values($ids)));
        foreach (array_chunk($rest, 200) as $chunk) {
          foreach ($storage->loadMultiple($chunk) as $reservation) {
            $phone_digits = preg_replace('/\D/', '', (string) $reservation->get('guest_phone')->value);
            if (strlen($phone_digits) === 11 && $phone_digits[0] === '8') {
              $phone_digits = '7' . substr($phone_digits, 1);
            }
            if ($phone_digits !== '' && $phone_digits === $account_digits) {
              $ids[] = $reservation->id();
            }
          }
        }
      }
      catch (\Throwable $e) {
        $this->getLogger('hotel_reservation')->warning('Phone fallback for my-bookings failed: @message', ['@message' => $e->getMessage()]);
      }
    }

    if (!empty($ids)) {
        /** @var \Drupal\hotel_reservation\Entity\Reservation[] $reservations */
        $reservations = $storage->loadMultiple($ids);
        uasort($reservations, function ($a, $b) {
          $a_date = $a->getCheckInDate() ? (int) $a->getCheckInDate()->format('U') : 0;
          $b_date = $b->getCheckInDate() ? (int) $b->getCheckInDate()->format('U') : 0;
          return $b_date <=> $a_date;
        });
        foreach ($reservations as $reservation) {
          $room = $reservation->getRoom();
          $check_in = $reservation->getCheckInDate();
          $check_out = $reservation->getCheckOutDate();
          $nights = 0;
          if ($check_in && $check_out) {
            $nights = (int) $check_in->diff($check_out)->format('%a');
          }
          $amenities = [];
          if ($room) {
            foreach (explode(',', $room->getAmenities()) as $amenity) {
              $amenity = trim($amenity);
              if ($amenity !== '') {
                $amenities[] = $amenity;
              }
            }
          }
          $guest_count = (int) $reservation->get('guest_count')->value;
          $bookings[] = [
            'id' => (int) $reservation->id(),
            'status' => $reservation->get('status')->value,
            'status_label' => $reservation->getStatusLabel(),
            'guest_name' => $reservation->get('guest_name')->value,
            'guest_count' => $guest_count,
            'nights_text' => $nights . ' ' . self::plural($nights, 'ночь', 'ночи', 'ночей'),
            'guests_text' => $guest_count . ' ' . self::plural($guest_count, 'гость', 'гостя', 'гостей'),
            'room_name' => $room ? $room->label() : $this->t('Номер удалён'),
            'room_image_url' => $room ? $room->getImageUrl() : NULL,
            'room_image_alt' => $room ? $room->getImageAlt() : '',
            'amenities' => $amenities,
            'check_in' => $check_in ? $check_in->format('d.m.Y') : '—',
            'check_out' => $check_out ? $check_out->format('d.m.Y') : '—',
            'nights' => $nights,
            'total_price' => number_format((float) $reservation->getTotalPrice(), 0, '.', ' '),
            'created' => \Drupal::service('date.formatter')->format($reservation->getCreatedTime(), 'short'),
          ];
        }
      }

    return [
      '#theme' => 'hotel_reservation_my_bookings',
      '#bookings' => $bookings,
      '#currency' => $currency,
      '#check_in_time' => $check_in_time,
      '#check_out_time' => $check_out_time,
      '#attached' => [
        'library' => [
          'hotel_reservation/my-bookings',
        ],
      ],
      '#cache' => [
        'tags' => ['hr_reservation_list'],
        'contexts' => ['user'],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Russian plural form helper.
   */
  protected static function plural(int $n, string $one, string $few, string $many): string {
    $n = abs($n) % 100;
    $d = $n % 10;
    if ($n > 10 && $n < 20) {
      return $many;
    }
    if ($d > 1 && $d < 5) {
      return $few;
    }
    if ($d === 1) {
      return $one;
    }
    return $many;
  }

}
