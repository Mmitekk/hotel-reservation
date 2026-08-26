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
    $header['name'] = $this->t('Room Name');
    $header['room_type'] = $this->t('Type');
    $header['capacity'] = $this->t('Capacity');
    $header['base_price'] = $this->t('Base Price');
    $header['status'] = $this->t('Status');
    $header['operations'] = $this->t('Operations');
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

    $row['name']['data'] = [
      '#type' => 'link',
      '#title' => $entity->label(),
      '#url' => $entity->toUrl('edit-form'),
    ];

    $room_type_options = [
      'standard' => 'Standard',
      'superior' => 'Superior',
      'deluxe' => 'Deluxe',
      'suite' => 'Suite',
      'apartment' => 'Apartment',
      'villa' => 'Villa',
      'family' => 'Family',
      'economy' => 'Economy',
    ];
    $rt = $entity->get('room_type')->value;
    $row['room_type']['data'] = [
      '#markup' => '<span class="hr-admin-room-type hr-admin-room-type--' . $rt . '">' . ($room_type_options[$rt] ?? $rt) . '</span>',
    ];

    $row['capacity'] = $entity->get('capacity')->value;

    $row['base_price'] = number_format((float) $entity->get('base_price')->value, 2, '.', ' ') . ' ' . $currency_symbol;

    if ($entity->get('status')->value) {
      $row['status']['data'] = [
        '#markup' => '<span class="badge badge-success">' . $this->t('Published') . '</span>',
      ];
    }
    else {
      $row['status']['data'] = [
        '#markup' => '<span class="badge badge-danger">' . $this->t('Unpublished') . '</span>',
      ];
    }

    $pricing_url = Url::fromRoute('hotel_reservation.room_pricing', [
      'hr_room' => $entity->id(),
    ]);
    $row['operations']['data'] = [
      '#type' => 'operations',
      '#links' => [
        'edit' => [
          'title' => $this->t('Edit'),
          'url' => $entity->toUrl('edit-form'),
        ],
        'delete' => [
          'title' => $this->t('Delete'),
          'url' => $entity->toUrl('delete-form'),
        ],
        'pricing' => [
          'title' => $this->t('Pricing'),
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
      '#empty' => $this->t('No rooms available.'),
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
            'title' => $this->t('Add room'),
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
