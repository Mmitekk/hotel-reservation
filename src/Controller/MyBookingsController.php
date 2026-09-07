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

    if ($email !== '') {
      $storage = $this->entityTypeManager()->getStorage('hr_reservation');
      $ids = $storage->getQuery()
        ->condition('guest_email', $email)
        ->sort('check_in', 'DESC')
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($ids)) {
        /** @var \Drupal\hotel_reservation\Entity\Reservation[] $reservations */
        $reservations = $storage->loadMultiple($ids);
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
