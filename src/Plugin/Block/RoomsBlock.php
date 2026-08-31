<?php

namespace Drupal\hotel_reservation\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;

/**
 * Provides a 'Hotel Rooms Gallery' block.
 *
 * @Block(
 *   id = "hotel_reservation_rooms",
 *   admin_label = @Translation("Наши номера"),
 *   category = @Translation("Бронирование отеля"),
 * )
 */
class RoomsBlock extends BlockBase {

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
   * Room type accent colors.
   *
   * @var string[]
   */
  private const ROOM_TYPE_COLORS = [
    'standard' => '#6b7280',
    'superior' => '#0ea5e9',
    'deluxe' => '#8b5cf6',
    'suite' => '#f59e0b',
    'apartment' => '#10b981',
    'villa' => '#ec4899',
    'family' => '#06b6d4',
    'economy' => '#64748b',
  ];

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'limit' => 8,
      'show_title' => TRUE,
      'show_description' => TRUE,
      'show_price' => TRUE,
      'show_amenities' => TRUE,
      'layout' => 'grid',
      'use_page_content_section' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Количество номеров'),
      '#description' => $this->t('Максимальное количество отображаемых номеров (от 1 до 50).'),
      '#default_value' => $config['limit'] ?? 8,
      '#min' => 1,
      '#max' => 50,
      '#required' => TRUE,
    ];

    $form['show_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Показывать название номера'),
      '#default_value' => $config['show_title'] ?? TRUE,
    ];

    $form['show_description'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Показывать описание'),
      '#default_value' => $config['show_description'] ?? TRUE,
    ];

    $form['show_price'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Показывать цену'),
      '#default_value' => $config['show_price'] ?? TRUE,
    ];

    $form['show_amenities'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Показывать удобства'),
      '#default_value' => $config['show_amenities'] ?? TRUE,
    ];

    $form['layout'] = [
      '#type' => 'select',
      '#title' => $this->t('Макет'),
      '#options' => [
        'grid' => $this->t('Сетка'),
        'carousel' => $this->t('Карусель'),
      ],
      '#default_value' => $config['layout'] ?? 'grid',
    ];

    $form['use_page_content_section'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Обёртка .page-content-section'),
      '#description' => $this->t('Обернуть содержимое блока в div с классом .page-content-section (добавляет отступы слева и справа).'),
      '#default_value' => $config['use_page_content_section'] ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['limit'] = (int) $form_state->getValue('limit');
    $this->configuration['show_title'] = (bool) $form_state->getValue('show_title');
    $this->configuration['show_description'] = (bool) $form_state->getValue('show_description');
    $this->configuration['show_price'] = (bool) $form_state->getValue('show_price');
    $this->configuration['show_amenities'] = (bool) $form_state->getValue('show_amenities');
    $this->configuration['layout'] = $form_state->getValue('layout');
    $this->configuration['use_page_content_section'] = (bool) $form_state->getValue('use_page_content_section');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $limit = max(1, min(50, $config['limit'] ?? 8));
    $showTitle = !empty($config['show_title']);
    $showDescription = !empty($config['show_description']);
    $showPrice = !empty($config['show_price']);
    $showAmenities = !empty($config['show_amenities']);
    $layout = $config['layout'] ?? 'grid';

    // Currency settings.
    $settingsConfig = \Drupal::config('hotel_reservation.settings');
    $currencySymbol = $settingsConfig->get('currency_symbol') ?: '₽';

    // Query published rooms ordered by sort_weight.
    $query = \Drupal::entityTypeManager()->getStorage('hr_room')->getQuery()
      ->condition('status', TRUE)
      ->sort('sort_weight', 'ASC')
      ->accessCheck(TRUE)
      ->range(0, $limit);
    $ids = $query->execute();

    if (empty($ids)) {
      return [
        '#markup' => '<div class="hr-rooms-empty">' . $this->t('Нет опубликованных номеров.') . '</div>',
        '#cache' => [
          'tags' => ['hr_room_list'],
          'max-age' => 0,
        ],
      ];
    }

    $rooms = \Drupal::entityTypeManager()->getStorage('hr_room')->loadMultiple($ids);

    $useSection = !empty($config['use_page_content_section']);
    $sectionOpen = $useSection ? '<div class="page-content-section">' : '';
    $sectionClose = $useSection ? '</div>' : '';

    $html = $sectionOpen . '<div class="hr-rooms-grid hr-rooms-grid--' . Html::escape($layout) . '">';

    foreach ($rooms as $room) {
      $roomType = $room->get('room_type')->value ?? 'standard';
      $typeLabel = self::ROOM_TYPE_LABELS[$roomType] ?? $roomType;
      $typeColor = self::ROOM_TYPE_COLORS[$roomType] ?? '#6b7280';

      $name = Html::escape($room->getName());

      // Description trimmed to 120 characters.
      $description = '';
      if ($showDescription) {
        $rawDesc = $room->getDescription() ?? '';
        $description = Html::escape(strlen($rawDesc) > 120 ? mb_substr($rawDesc, 0, 120) . '...' : $rawDesc);
      }

      // Format price.
      $priceFormatted = '';
      if ($showPrice) {
        $priceValue = (float) $room->getBasePrice();
        $priceFormatted = number_format($priceValue, 0, '.', ' ') . ' ' . Html::escape($currencySymbol);
      }

      // Parse amenities.
      $amenitiesHtml = '';
      if ($showAmenities) {
        $rawAmenities = $room->getAmenities();
        if (!empty($rawAmenities)) {
          $amenityItems = array_map('trim', explode(',', $rawAmenities));
          $amenityItems = array_filter($amenityItems);
          $amenitiesHtml = '<div class="hr-room-card__amenities">';
          foreach ($amenityItems as $amenity) {
            $amenitiesHtml .= '<span class="hr-room-card__amenity">' . Html::escape($amenity) . '</span>';
          }
          $amenitiesHtml .= '</div>';
        }
      }

      $capacity = (int) $room->getCapacity();
      $capacityLabel = $this->t('До @count @guests', [
        '@count' => $capacity,
        '@guests' => $capacity === 1 ? $this->t('гостя') : $this->t('гостей'),
      ]);

      $html .= '<div class="hr-room-card" style="--room-color: ' . Html::escape($typeColor) . '">';

      // Header: type badge + price.
      $html .= '<div class="hr-room-card__header">';
      $html .= '<span class="hr-room-card__type-badge" style="background:var(--room-color);color:#fff">' . Html::escape($typeLabel) . '</span>';
      if ($showPrice) {
        $html .= '<div class="hr-room-card__price">' . $priceFormatted . '<span class="hr-room-card__price-unit">/' . $this->t('ночь') . '</span></div>';
      }
      $html .= '</div>';

      // Name.
      if ($showTitle) {
        $html .= '<h3 class="hr-room-card__name">' . $name . '</h3>';
      }

      // Description.
      if ($showDescription && !empty($description)) {
        $html .= '<p class="hr-room-card__desc">' . $description . '</p>';
      }

      // Amenities.
      if ($showAmenities && !empty($amenitiesHtml)) {
        $html .= $amenitiesHtml;
      }

      // Footer: capacity.
      $html .= '<div class="hr-room-card__footer">';
      $html .= '<span class="hr-room-card__capacity">👥 ' . Html::escape($capacityLabel) . '</span>';
      $html .= '</div>';

      $html .= '</div>';
    }

    $html .= '</div>';
    $html .= $sectionClose;

    return [
      '#markup' => $html,
      '#attached' => [
        'library' => [
          'hotel_reservation/rooms-block',
        ],
      ],
      '#cache' => [
        'tags' => ['hr_room_list'],
        'max-age' => 0,
      ],
      '#allowed_tags' => [
        'div', 'h3', 'p', 'span',
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
