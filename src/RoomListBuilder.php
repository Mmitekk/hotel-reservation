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
      $image_alt = $entity->getImageAlt();
    }
    if (!empty($image_url)) {
      $row['image']['data'] = [
        '#markup' => '<img class="hr-room-thumb" src="' . htmlspecialchars($image_url, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($image_alt, ENT_QUOTES, 'UTF-8') . '" style="width:64px;height:48px;object-fit:cover;border-radius:6px;">',
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

    $room_type_options = function_exists('hotel_reservation_room_type_allowed_values') ? hotel_reservation_room_type_allowed_values() : [
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
    $colorMap = [];
    try {
      foreach (\Drupal::entityTypeManager()->getStorage('hr_room_type')->loadMultiple() as $rt_entity) {
        $colorMap[$rt_entity->id()] = $rt_entity->getColor();
      }
    }
    catch (\Exception $e) {
    }
    $bg = $colorMap[$rt] ?? '#6b7280';
    $row['room_type']['data'] = [
      '#markup' => '<span class="hr-admin-room-type" style="background:' . htmlspecialchars($bg, ENT_QUOTES, 'UTF-8') . ';color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;">' . htmlspecialchars($room_type_options[$rt] ?? $rt, ENT_QUOTES, 'UTF-8') . '</span>',
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
    $account_ops = \Drupal::currentUser();
    $can_admin_ops = $account_ops->hasPermission('administer hotel reservation');
    $can_edit_room = $can_admin_ops || $account_ops->hasPermission('edit hotel rooms');
    $can_delete_room = $can_admin_ops || $account_ops->hasPermission('delete hotel rooms');
    $op_links = [];
    if ($can_edit_room) {
      $op_links['edit'] = [
        'title' => $this->t('Изменить'),
        'url' => $entity->toUrl('edit-form'),
      ];
    }
    if ($can_delete_room) {
      $op_links['delete'] = [
        'title' => $this->t('Удалить'),
        'url' => $entity->toUrl('delete-form'),
      ];
    }
    if ($can_edit_room) {
      $op_links['pricing'] = [
        'title' => $this->t('Цены'),
        'url' => $pricing_url,
      ];
    }
    if (!empty($op_links)) {
      $row['operations']['data'] = [
        '#type' => 'operations',
        '#links' => $op_links,
      ];
    }
    else {
      $row['operations'] = '';
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['title'] = [
      '#markup' => '<h1 class="hr-admin-page-title">' . $this->t('Номера') . '</h1>',
      '#weight' => -100,
    ];
    $account_rooms = \Drupal::currentUser();
    $can_add_room = $account_rooms->hasPermission('administer hotel reservation')
      || $account_rooms->hasPermission('create hotel rooms');
    if ($can_add_room) {
      try {
        $add_url = Url::fromRoute('entity.hr_room.add_form');
        $build['add_link'] = [
          '#type' => 'link',
          '#title' => $this->t('＋ Добавить номер'),
          '#url' => $add_url,
          '#attributes' => ['class' => ['button', 'button--primary', 'hr-rooms-list__add']],
          '#weight' => -90,
        ];
      }
      catch (\Symfony\Component\Routing\Exception\RouteNotFoundException $e) {
      }
    }
    $build['#prefix'] = '<div class="wrapper"><div class="hr-rooms-list">';
    $build['#suffix'] = '</div></div>';
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#rows' => [],
      '#empty' => $this->t('Нет номеров. <a href=":url">Добавить номер</a>.'),
      '#attributes' => [
        'class' => ['table-responsive'],
      ],
    ];

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
