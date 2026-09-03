<?php

namespace Drupal\hotel_reservation;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a list builder for Room entities.
 */
class RoomListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['image'] = $this->t('Превью');
    $header['name'] = $this->t('Название');
    $header['room_type'] = $this->t('Тип');
    $header['capacity'] = $this->t('Вместимость');
    $header['base_price'] = $this->t('Базовая цена');
    $header['status'] = $this->t('Статус');
    $header['operations'] = $this->t('Действия');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $currency_symbol = '₽';
    $config = \Drupal::config('hotel_reservation.settings');
    if ($config->get('currency_symbol')) {
      $currency_symbol = $config->get('currency_symbol');
    }

    $image_url = NULL;
    $image_alt = '';
    if (method_exists($entity, 'getImageUrl')) {
      $image_url = $entity->getImageUrl();
      $img_field = $entity->get('image')->first();
      if ($img_field) {
        $image_alt = $img_field->alt ?? '';
      }
    }
    if (!empty($image_url)) {
      $row['image']['data'] = [
        '#markup' => '<img src="' . htmlspecialchars($image_url, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($image_alt, ENT_QUOTES, 'UTF-8') . '" style="width:64px;height:48px;object-fit:cover;border-radius:6px;">',
      ];
    }
    else {
      $row['image']['data'] = [
        '#markup' => '<span style="display:inline-block;width:64px;height:48px;background:#f3f4f6;border-radius:6px;text-align:center;line-height:48px;color:#9ca3af;font-size:11px;">—</span>',
      ];
    }

    $row['name']['data'] = [
      '#type' => 'link',
      '#title' => $entity->label(),
      '#url' => $entity->toUrl('edit-form'),
    ];

    $room_type_options = [
      'standard' => 'Стандарт',
      'superior' => 'Супериор',
      'deluxe' => 'Делюкс',
      'suite' => 'Сьют',
      'apartment' => 'Апартаменты',
      'villa' => 'Вилла',
      'family' => 'Семейный',
      'economy' => 'Эконом',
    ];
    $rt = $entity->get('room_type')->value;
    $row['room_type']['data'] = [
      '#markup' => '<span class="hr-admin-room-type hr-admin-room-type--' . $rt . '">' . ($room_type_options[$rt] ?? $rt) . '</span>',
    ];

    $row['capacity'] = $entity->get('capacity')->value;

    $row['base_price'] = number_format((float) $entity->get('base_price')->value, 2, '.', ' ') . ' ' . $currency_symbol;

    if ($entity->get('status')->value) {
      $row['status']['data'] = [
        '#markup' => '<span class="badge badge-success">' . $this->t('Опубликован') . '</span>',
      ];
    }
    else {
      $row['status']['data'] = [
        '#markup' => '<span class="badge badge-danger">' . $this->t('Скрыт') . '</span>',
      ];
    }

    $pricing_url = Url::fromRoute('hotel_reservation.room_pricing', [
      'hr_room' => $entity->id(),
    ]);
    $row['operations']['data'] = [
      '#type' => 'operations',
      '#links' => [
        'edit' => [
          'title' => $this->t('Изменить'),
          'url' => $entity->toUrl('edit-form'),
        ],
        'delete' => [
          'title' => $this->t('Удалить'),
          'url' => $entity->toUrl('delete-form'),
        ],
        'pricing' => [
          'title' => $this->t('Цены'),
          'url' => $pricing_url,
        ],
      ],
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#rows' => [],
      '#empty' => $this->t('Нет номеров. <a href=":url">Добавить номер</a>.'),
      '#attributes' => [
        'class' => ['table-responsive'],
      ],
    ];

    // Add "Add room" link only if the route exists.
    try {
      $add_url = Url::fromRoute('entity.hr_room.add_form');
      $build['add_link'] = [
        '#type' => 'operations',
        '#links' => [
          'add_room' => [
            'title' => $this->t('Добавить номер'),
            'url' => $add_url,
          ],
        ],
        '#attributes' => ['style' => 'margin-bottom: 1rem;'],
      ];
    }
    catch (\Symfony\Component\Routing\Exception\RouteNotFoundException $e) {
      // Route not available — skip the link.
    }

    foreach ($this->load() as $entity) {
      if ($row = $this->buildRow($entity)) {
        $build['table']['#rows'][$entity->id()] = $row;
      }
    }

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

}
