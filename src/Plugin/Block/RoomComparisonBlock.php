<?php

namespace Drupal\hotel_reservation\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;

/**
 * Provides a 'Room Comparison' block.
 *
 * @Block(
 *   id = "hotel_reservation_room_comparison",
 *   admin_label = @Translation("Сравнение номеров"),
 *   category = @Translation("Бронирование отеля"),
 * )
 */
class RoomComparisonBlock extends BlockBase {

  /**
   * Room type labels in Russian.
   *
   * @var string[]
   */
  private const ROOM_TYPE_LABELS = [
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
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'use_page_content_section' => FALSE,
      'use_block_spacing' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form['use_page_content_section'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Обёртка .page-content-section'),
      '#description' => $this->t('Обернуть содержимое блока в div с классом .page-content-section (добавляет отступы слева и справа).'),
      '#default_value' => $config['use_page_content_section'] ?? FALSE,
    ];

    $form['use_block_spacing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Отступы блока (margin-top/bottom: 5rem)'),
      '#description' => $this->t('Добавить вертикальные отступы 5rem сверху и снизу блока.'),
      '#default_value' => $config['use_block_spacing'] ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['use_page_content_section'] = (bool) $form_state->getValue('use_page_content_section');
    $this->configuration['use_block_spacing'] = (bool) $form_state->getValue('use_block_spacing');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Currency settings.
    $settingsConfig = \Drupal::config('hotel_reservation.settings');
    $currencySymbol = $settingsConfig->get('currency_symbol') ?: '₽';

    // Query all published rooms sorted by sort_weight.
    $query = \Drupal::entityTypeManager()->getStorage('hr_room')->getQuery()
      ->condition('status', TRUE)
      ->sort('sort_weight', 'ASC')
      ->accessCheck(TRUE);
    $ids = $query->execute();

    $roomsData = [];

    if (!empty($ids)) {
      $rooms = \Drupal::entityTypeManager()->getStorage('hr_room')->loadMultiple($ids);

      foreach ($rooms as $room) {
        $roomType = $room->get('room_type')->value ?? 'standard';
        $typeLabel = self::ROOM_TYPE_LABELS[$roomType] ?? $roomType;
        $priceValue = (float) $room->getBasePrice();

        // Parse and sort amenities.
        $amenitiesRaw = $room->getAmenities();
        $amenities = [];
        if (!empty($amenitiesRaw)) {
          $amenities = array_values(array_filter(array_map('trim', explode(',', $amenitiesRaw))));
          sort($amenities);
        }
        $amenitiesString = implode(', ', $amenities);

        // Truncate description for comparison.
        $description = $room->getDescription() ?? '';

        $roomsData[] = [
          'id' => (int) $room->id(),
          'name' => $room->getName(),
          'room_type_label' => $typeLabel,
          'capacity' => $room->getCapacity(),
          'base_price_formatted' => number_format($priceValue, 0, '.', ' ') . ' ' . $currencySymbol,
          'amenities' => $amenities,
          'amenities_string' => $amenitiesString,
          'description' => $description,
        ];
      }
    }

    // Build the selector HTML.
    $html = '<div class="hr-room-comparison">';
    $html .= '<div class="hr-comparison-selector">';
    $html .= '<div class="hr-comparison-selector__header">';
    $html .= '<span class="hr-comparison-selector__count">Выберите номера (0/3)</span>';
    $html .= '<button class="hr-comparison-selector__reset" style="display:none">Сбросить</button>';
    $html .= '</div>';
    $html .= '<div class="hr-comparison-selector__grid">';

    $checkSvg = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    foreach ($roomsData as $room) {
      $html .= '<button class="hr-comparison-room-btn" data-room-id="' . $room['id'] . '">';
      $html .= '<div class="hr-comparison-room-btn__check">' . $checkSvg . '</div>';
      $html .= '<div class="hr-comparison-room-btn__info">';
      $html .= '<span class="hr-comparison-room-btn__name">' . Html::escape($room['name']) . '</span>';
      $html .= '<span class="hr-comparison-room-btn__type">' . Html::escape($room['room_type_label']) . '</span>';
      $html .= '</div>';
      $html .= '</button>';
    }

    $html .= '</div>';
    $html .= '</div>';

    // Empty state (shown by default).
    $html .= '<div class="hr-comparison-empty">';
    $html .= '<div class="hr-comparison-empty__icon">⊘</div>';
    $html .= '<p class="hr-comparison-empty__text">Выберите 2–3 номера для сравнения</p>';
    $html .= '</div>';

    // Comparison table wrapper (hidden by default).
    $html .= '<div class="hr-comparison-table-wrapper" style="display:none">';
    $html .= '<table class="hr-comparison-table">';
    $html .= '<thead><tr><th class="hr-comparison-table__label-col">Параметр</th></tr></thead>';
    $html .= '<tbody></tbody>';
    $html .= '</table>';
    $html .= '</div>';

    $html .= '</div>';

    return [
      '#markup' => $html,
      '#attached' => [
        'library' => [
          'hotel_reservation/room-comparison',
        ],
        'drupalSettings' => [
          'hotelReservation' => [
            'comparisonRooms' => $roomsData,
          ],
        ],
      ],
      '#cache' => [
        'tags' => ['hr_room_list'],
        'max-age' => 0,
      ],
      '#allowed_tags' => [
        'div', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'button', 'span', 'p', 'svg', 'path',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['hr_room_list'];
  }

}
