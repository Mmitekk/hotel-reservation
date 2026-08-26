<?php

namespace Drupal\hotel_reservation;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a list builder for Reservation entities with filter form.
 */
class ReservationListBuilder extends EntityListBuilder {

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The currency symbol.
   *
   * @var string
   */
  protected $currencySymbol;

  /**
   * {@inheritdoc}
   */
  public function __construct($entity_type, $entity_storage) {
    parent::__construct($entity_type, $entity_storage);
    $this->request = \Drupal::request();
    $config = \Drupal::config('hotel_reservation.settings');
    $this->currencySymbol = $config->get('currency_symbol') ?: '₽';
  }

  /**
   * Gets the color class for a reservation status.
   *
   * @param string $status
   *   The reservation status value.
   *
   * @return string
   *   A CSS class name for the badge color.
   */
  protected function getStatusColorClass(string $status): string {
    $map = [
      'pending' => 'badge-warning',
      'confirmed' => 'badge-info',
      'checked_in' => 'badge-primary',
      'checked_out' => 'badge-success',
      'cancelled' => 'badge-danger',
      'expired' => 'badge-secondary',
    ];
    return $map[$status] ?? 'badge-secondary';
  }

  /**
   * Builds the filter form.
   *
   * @return array
   *   A render array for the filter form.
   */
  protected function buildFilterForm(): array {
    $status = $this->request->query->get('status', '');
    $date_from = $this->request->query->get('date_from', '');
    $date_to = $this->request->query->get('date_to', '');
    $room = $this->request->query->get('room', '');

    $status_options = ['' => $this->t('- All statuses -')] + \Drupal\hotel_reservation\Entity\Reservation::getStatusOptions();

    $room_entities = \Drupal::entityTypeManager()->getStorage('hr_room')->loadMultiple();
    $room_options = ['' => $this->t('- All rooms -')];
    foreach ($room_entities as $room_entity) {
      $room_options[$room_entity->id()] = $room_entity->label();
    }

    $form = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['reservation-filter-form', 'container-inline'],
        'style' => 'margin-bottom: 1rem;',
      ],
    ];

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => $status_options,
      '#default_value' => $status,
      '#attributes' => ['name' => 'status'],
    ];

    $form['date_from'] = [
      '#type' => 'date',
      '#title' => $this->t('Date from'),
      '#default_value' => $date_from,
      '#attributes' => ['name' => 'date_from'],
    ];

    $form['date_to'] = [
      '#type' => 'date',
      '#title' => $this->t('Date to'),
      '#default_value' => $date_to,
      '#attributes' => ['name' => 'date_to'],
    ];

    $form['room'] = [
      '#type' => 'select',
      '#title' => $this->t('Room'),
      '#options' => $room_options,
      '#default_value' => $room,
      '#attributes' => ['name' => 'room'],
    ];

    $form['submit'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $this->t('Filter'),
      '#attributes' => [
        'type' => 'submit',
        'class' => ['button', 'button--primary'],
      ],
    ];

    $form['reset'] = [
      '#type' => 'link',
      '#title' => $this->t('Reset'),
      '#url' => Url::fromRoute('entity.hr_reservation.collection'),
      '#attributes' => [
        'class' => ['button'],
        'style' => 'margin-left: 0.5rem;',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->accessCheck(FALSE);

    $status = $this->request->query->get('status', '');
    if ($status !== '') {
      $query->condition('status', $status);
    }

    $date_from = $this->request->query->get('date_from', '');
    if ($date_from !== '') {
      $query->condition('check_in', $date_from, '>=');
    }

    $date_to = $this->request->query->get('date_to', '');
    if ($date_to !== '') {
      $query->condition('check_out', $date_to, '<=');
    }

    $room = $this->request->query->get('room', '');
    if ($room !== '') {
      $query->condition('room_id', (int) $room);
    }

    // Only pass sortable columns to tableSort (parent::buildHeader adds
    // a checkbox column without 'field', which breaks addSort).
    $sortable_header = [];
    foreach ($this->buildHeader() as $key => $col) {
      if (isset($col['field']) && $col['field'] !== '') {
        $sortable_header[$key] = $col;
      }
    }
    $query->tableSort($sortable_header);
    $query->pager($this->limit);

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['guest_name'] = [
      'data' => $this->t('Guest'),
      'field' => 'guest_name',
      'spec' => [
        'column' => 'guest_name',
      ],
      'sort' => 'asc',
    ];
    $header['room'] = $this->t('Room');
    $header['check_in'] = [
      'data' => $this->t('Check-in'),
      'field' => 'check_in',
      'spec' => [
        'column' => 'check_in',
      ],
    ];
    $header['check_out'] = [
      'data' => $this->t('Check-out'),
      'field' => 'check_out',
      'spec' => [
        'column' => 'check_out',
      ],
    ];
    $header['status'] = [
      'data' => $this->t('Status'),
      'field' => 'status',
      'spec' => [
        'column' => 'status',
      ],
    ];
    $header['total_price'] = [
      'data' => $this->t('Total'),
      'field' => 'total_price',
      'spec' => [
        'column' => 'total_price',
      ],
    ];
    $header['operations'] = $this->t('Operations');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    // Guest name as a link to the canonical view.
    $row['guest_name']['data'] = [
      '#type' => 'link',
      '#title' => $entity->get('guest_name')->value,
      '#url' => $entity->toUrl('canonical'),
    ];

    // Room name.
    $room = $entity->get('room_id')->entity;
    if ($room) {
      $row['room']['data'] = [
        '#type' => 'link',
        '#title' => $room->label(),
        '#url' => $room->toUrl('edit-form'),
      ];
    }
    else {
      $row['room'] = $this->t('N/A');
    }

    // Check-in date formatted d.m.Y.
    $check_in_value = $entity->get('check_in')->value;
    if ($check_in_value) {
      $check_in_date = new \DateTime($check_in_value);
      $row['check_in'] = $check_in_date->format('d.m.Y');
    }
    else {
      $row['check_in'] = '';
    }

    // Check-out date formatted d.m.Y.
    $check_out_value = $entity->get('check_out')->value;
    if ($check_out_value) {
      $check_out_date = new \DateTime($check_out_value);
      $row['check_out'] = $check_out_date->format('d.m.Y');
    }
    else {
      $row['check_out'] = '';
    }

    // Status with color-coded badge.
    $status_value = $entity->get('status')->value;
    $status_label = \Drupal\hotel_reservation\Entity\Reservation::getStatusOptions()[$status_value] ?? $status_value;
    $color_class = $this->getStatusColorClass($status_value);
    $row['status']['data'] = [
      '#markup' => '<span class="badge ' . $color_class . '">' . htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8') . '</span>',
    ];

    // Total price.
    $total_price = number_format((float) $entity->get('total_price')->value, 2, '.', ' ') . ' ' . $this->currencySymbol;
    $row['total_price'] = $total_price;

    // Operations: edit, delete, and status change links.
    $operations = [];
    $operations['edit'] = [
      'title' => $this->t('Edit'),
      'url' => $entity->toUrl('edit-form'),
    ];
    $operations['delete'] = [
      'title' => $this->t('Delete'),
      'url' => $entity->toUrl('delete-form'),
    ];

    // Add quick status change links.
    $status_transitions = $this->getStatusTransitions($status_value);
    foreach ($status_transitions as $transition_status => $transition_label) {
      $operations['status_' . $transition_status] = [
        'title' => $transition_label,
        'url' => Url::fromRoute('hotel_reservation.reservation_status', [
          'hr_reservation' => $entity->id(),
          'status' => $transition_status,
        ]),
      ];
    }

    $row['operations']['data'] = [
      '#type' => 'operations',
      '#links' => $operations,
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * Returns available status transitions for a given status.
   *
   * @param string $current_status
   *   The current status value.
   *
   * @return array
   *   An associative array of target status values to labels.
   */
  protected function getStatusTransitions(string $current_status): array {
    $transitions = [
      'pending' => [
        'confirmed' => $this->t('Confirm'),
        'cancelled' => $this->t('Cancel'),
      ],
      'confirmed' => [
        'checked_in' => $this->t('Check in'),
        'cancelled' => $this->t('Cancel'),
      ],
      'checked_in' => [
        'checked_out' => $this->t('Check out'),
      ],
      'checked_out' => [],
      'cancelled' => [],
      'expired' => [],
    ];

    return $transitions[$current_status] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['filter'] = $this->buildFilterForm();

    $build['filter']['actions'] = [
      '#type' => 'container',
      '#attributes' => ['style' => 'margin-bottom: 12px; display: flex; align-items: center; gap: 8px;'],
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Export CSV ↓'),
        '#url' => Url::fromRoute('hotel_reservation.export_csv'),
        '#attributes' => ['class' => ['button', 'hr-dashboard-btn', 'hr-dashboard-btn--export']],
      ],
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#rows' => [],
      '#empty' => $this->t('No reservations found.'),
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
