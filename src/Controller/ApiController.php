<?php

namespace Drupal\hotel_reservation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * API controller for Hotel Reservation frontend endpoints.
 */
class ApiController extends ControllerBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * Checks room availability and returns available rooms with prices.
   *
   * Accepts JSON POST data: check_in, check_out, guest_count.
   * Returns JSON: [{id, name, capacity, base_price, total_price, nights, available}].
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with available rooms.
   */
  public function checkAvailability(Request $request) {
    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    if (empty($data)) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Неверные данные JSON.',
      ], 400);
    }

    $check_in = $data['check_in'] ?? '';
    $check_out = $data['check_out'] ?? '';
    $guest_count = (int) ($data['guest_count'] ?? 1);

    // Validate dates.
    if (empty($check_in) || empty($check_out)) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Укажите check_in и check_out.',
      ], 400);
    }

    if (!$this->validateDate($check_in) || !$this->validateDate($check_out)) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Неверный формат даты. Используйте Г-М-Д.',
      ], 400);
    }

    if ($check_out <= $check_in) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Дата выезда должна быть позже даты заезда.',
      ], 400);
    }

    if ($guest_count < 1) {
      $guest_count = 1;
    }

    // Validate against config min/max stay.
    $config = $this->config('hotel_reservation.settings');
    $min_stay = (int) $config->get('min_stay_nights') ?: 1;
    $max_stay = (int) $config->get('max_stay_nights') ?: 30;
    $nights = (new \DateTime($check_out))->diff(new \DateTime($check_in))->days;

    if ($nights < $min_stay) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Минимальное количество ночей: ' . $min_stay . '.',
        'min_stay' => $min_stay,
      ], 400);
    }

    if ($nights > $max_stay) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Максимальное количество ночей: ' . $max_stay . '.',
        'max_stay' => $max_stay,
      ], 400);
    }

    // Get available rooms.
    $available_rooms = hotel_reservation_get_available_rooms($check_in, $check_out, $guest_count);

    $results = [];
    foreach ($available_rooms as $room) {
      $pricing = hotel_reservation_calculate_price($room->id(), $check_in, $check_out);

      $rawDesc = $room->getDescription() ?: '';
      $plainDesc = trim(strip_tags(html_entity_decode($rawDesc, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
      $plainDesc = html_entity_decode($plainDesc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $plainDesc = preg_replace('/\s+/', ' ', $plainDesc);
      $results[] = [
        'id' => (int) $room->id(),
        'name' => $room->label(),
        'description' => $plainDesc,
        'capacity' => $room->getCapacity(),
        'base_price' => number_format((float) $room->getBasePrice(), 2, '.', ''),
        'total_price' => number_format($pricing['total'], 2, '.', ''),
        'nights' => $pricing['nights'],
        'available' => TRUE,
        'amenities' => $room->getAmenities(),
      ];
    }

    return new SymfonyJsonResponse([
      'success' => TRUE,
      'rooms' => $results,
      'check_in' => $check_in,
      'check_out' => $check_out,
      'nights' => $nights,
      'guest_count' => $guest_count,
    ]);
  }

  /**
   * Submits a new reservation from the frontend booking form.
   *
   * Accepts JSON POST data: room_id, check_in, check_out, guest_name,
   * guest_phone, guest_email, guest_count, notes.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with success/error.
   */
  public function submitReservation(Request $request) {
    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    if (empty($data)) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Неверные данные JSON.',
      ], 400);
    }

    $room_id = (int) ($data['room_id'] ?? 0);
    $check_in = $data['check_in'] ?? '';
    $check_out = $data['check_out'] ?? '';
    $guest_name = trim($data['guest_name'] ?? '');
    $guest_phone = trim($data['guest_phone'] ?? '');
    $guest_email = trim($data['guest_email'] ?? '');
    $guest_count = (int) ($data['guest_count'] ?? 1);
    $notes = trim($data['notes'] ?? '');

    // Validate required fields.
    $errors = [];

    if (empty($room_id)) {
      $errors[] = 'Укажите room_id.';
    }
    if (empty($check_in) || !$this->validateDate($check_in)) {
      $errors[] = 'Укажите корректную дату заезда (Г-М-Д).';
    }
    if (empty($check_out) || !$this->validateDate($check_out)) {
      $errors[] = 'Укажите корректную дату выезда (Г-М-Д).';
    }
    if ($check_out <= $check_in) {
      $errors[] = 'Дата выезда должна быть позже даты заезда.';
    }
    if (empty($guest_name)) {
      $errors[] = 'Укажите имя гостя.';
    }
    if (empty($guest_phone)) {
      $errors[] = 'Укажите телефон гостя.';
    }
    if (!empty($guest_email) && !\Drupal::service('email.validator')->isValid($guest_email)) {
      $errors[] = 'Некорректный email.';
    }
    if ($guest_count < 1) {
      $errors[] = 'Количество гостей должно быть не менее 1.';
    }

    // Validate against config min/max stay.
    $config = $this->config('hotel_reservation.settings');
    $min_stay = (int) $config->get('min_stay_nights') ?: 1;
    $max_stay = (int) $config->get('max_stay_nights') ?: 30;
    $nights = (new \DateTime($check_out))->diff(new \DateTime($check_in))->days;

    if ($nights < $min_stay) {
      $errors[] = 'Минимальное количество ночей: ' . $min_stay . '.';
    }
    if ($nights > $max_stay) {
      $errors[] = 'Максимальное количество ночей: ' . $max_stay . '.';
    }

    if (!empty($errors)) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'errors' => $errors,
      ], 400);
    }

    // Validate room exists and is published.
    $room = $this->entityTypeManager->getStorage('hr_room')->load($room_id);
    if (!$room) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Номер не найден.',
      ], 404);
    }
    if (!$room->isPublished()) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Номер недоступен для бронирования.',
      ], 400);
    }
    if ($room->getCapacity() < $guest_count) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Вместимость номера — ' . $room->getCapacity() . ' гостей, запрошено — ' . $guest_count . '.',
      ], 400);
    }

    // Check availability.
    $available_rooms = hotel_reservation_get_available_rooms($check_in, $check_out, $guest_count);
    if (!isset($available_rooms[$room_id])) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Номер занят на выбранные даты.',
      ], 409);
    }

    // Calculate price.
    $pricing = hotel_reservation_calculate_price($room_id, $check_in, $check_out);
    $total_price = number_format($pricing['total'], 2, '.', '');

    // Create the reservation entity.
    try {
      $reservation = $this->entityTypeManager->getStorage('hr_reservation')->create([
        'room_id' => ['target_id' => $room_id],
        'check_in' => $check_in,
        'check_out' => $check_out,
        'guest_name' => $guest_name,
        'guest_phone' => $guest_phone,
        'guest_email' => $guest_email,
        'guest_count' => $guest_count,
        'total_price' => $total_price,
        'notes' => ['value' => $notes, 'format' => 'plain_text'],
        'status' => 'pending',
      ]);
      $reservation->save();
    }
    catch (\Exception $e) {
      $this->getLogger('hotel_reservation')->error('Failed to create reservation: @message | File: @file Line: @line', [
        '@message' => $e->getMessage(),
        '@file' => $e->getFile(),
        '@line' => $e->getLine(),
      ]);
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Не удалось создать бронирование. Попробуйте ещё раз.',
      ], 500);
    }

    // Send confirmation email.
    $hotel_name = $config->get('hotel_name') ?: $this->config('system.site')->get('name');
    $currency = $config->get('currency_symbol') ?: '₽';
    $langcode = $this->languageManager()->getCurrentLanguage()->getId();

    // Send admin notification.
    if ((bool) $config->get('enable_admin_notification')) {
      $admin_email = $config->get('admin_notification_email');
      if (!empty($admin_email)) {
        $params['guest_name'] = $guest_name;
        $params['message'] = $this->t(
          "Создано новое бронирование через сайт:\n\nГость: @guest\nEmail: @email\nТелефон: @phone\nНомер: @room\nЗаезд: @check_in\nВыезд: @check_out\nГости: @count\nИтого: @total @currency\nЗаметки: @notes",
          [
            '@guest' => $guest_name,
            '@email' => $guest_email ?: '—',
            '@phone' => $guest_phone,
            '@room' => $room->label(),
            '@check_in' => (new \DateTime($check_in))->format('d.m.Y'),
            '@check_out' => (new \DateTime($check_out))->format('d.m.Y'),
            '@count' => $guest_count,
            '@total' => $total_price,
            '@currency' => $currency,
            '@notes' => $notes ?: 'Нет',
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

    // Auto-confirm if configured and guest email provided.
    if ((bool) $config->get('enable_guest_confirmation') && !empty($guest_email)) {
      // Send a pending notification (not yet confirmed).
      $params2['message'] = $this->t(
        "Уважаемый(ая) @guest,\n\nВаша заявка на бронирование в отеле @hotel получена и ожидает подтверждения.\n\nНомер: @room\nЗаезд: @check_in\nВыезд: @check_out\nГости: @count\nИтого: @total @currency\n\nВы получите подтверждение после обработки заявки.\n\nС уважением,\n@hotel",
        [
          '@guest' => $guest_name,
          '@hotel' => $hotel_name,
          '@room' => $room->label(),
          '@check_in' => (new \DateTime($check_in))->format('d.m.Y'),
          '@check_out' => (new \DateTime($check_out))->format('d.m.Y'),
          '@count' => $guest_count,
          '@total' => $total_price,
          '@currency' => $currency,
        ]
      );

      \Drupal::service('plugin.manager.mail')->mail(
        'hotel_reservation',
        'reservation_confirmation',
        $guest_email,
        $langcode,
        $params2
      );
    }

    return new SymfonyJsonResponse([
      'success' => TRUE,
      'message' => $this->t('Бронирование создано. Ваша заявка ожидает подтверждения.'),
      'reservation_id' => (int) $reservation->id(),
    ]);
  }

  /**
   * Returns price breakdown for a specific room.
   *
   * @param int $room_id
   *   The room entity ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request with check_in and check_out query params.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with price details.
   */
  public function getRoomPrices($room_id, Request $request) {
    $check_in = $request->query->get('check_in', '');
    $check_out = $request->query->get('check_out', '');

    if (empty($room_id) || empty($check_in) || empty($check_out)) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Укажите параметры room_id, check_in и check_out.',
      ], 400);
    }

    if (!$this->validateDate($check_in) || !$this->validateDate($check_out)) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Неверный формат даты. Используйте Г-М-Д.',
      ], 400);
    }

    if ($check_out <= $check_in) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Дата выезда должна быть позже даты заезда.',
      ], 400);
    }

    // Load the room.
    $room = $this->entityTypeManager->getStorage('hr_room')->load($room_id);
    if (!$room) {
      return new SymfonyJsonResponse([
        'success' => FALSE,
        'message' => 'Номер не найден.',
      ], 404);
    }

    $pricing = hotel_reservation_calculate_price($room_id, $check_in, $check_out);
    $config = $this->config('hotel_reservation.settings');
    $currency = $config->get('currency_symbol') ?: '₽';

    // Format daily prices for the response.
    $daily = [];
    foreach ($pricing['daily_prices'] as $date => $price) {
      $daily[] = [
        'date' => $date,
        'price' => number_format($price, 2, '.', ''),
        'formatted' => number_format($price, 2) . ' ' . $currency,
      ];
    }

    return new SymfonyJsonResponse([
      'success' => TRUE,
      'room_id' => (int) $room_id,
      'room_name' => $room->label(),
      'base_price' => number_format($pricing['base_price'], 2, '.', ''),
      'nights' => $pricing['nights'],
      'total_price' => number_format($pricing['total'], 2, '.', ''),
      'formatted_total' => number_format($pricing['total'], 2) . ' ' . $currency,
      'currency' => $currency,
      'daily_prices' => $daily,
    ]);
  }

  /**
   * Validates a date string in Y-m-d format.
   *
   * @param string $date
   *   The date string to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function validateDate(string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      return FALSE;
    }
    $parts = explode('-', $date);
    return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
  }

}
