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

    $status_options = ['' => $this->t('— Все статусы —')] + \Drupal\hotel_reservation\Entity\Reservation::getStatusOptions();

    $room_entities = \Drupal::entityTypeManager()->getStorage('hr_room')->loadMultiple();
    $room_options = ['' => $this->t('— Все номера —')];
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
      '#title' => $this->t('Статус'),
      '#options' => $status_options,
      '#default_value' => $status,
      '#attributes' => ['name' => 'status'],
    ];

    $form['date_from'] = [
      '#type' => 'date',
      '#title' => $this->t('Дата с'),
      '#default_value' => $date_from,
      '#attributes' => ['name' => 'date_from'],
    ];

    $form['date_to'] = [
      '#type' => 'date',
      '#title' => $this->t('Дата по'),
      '#default_value' => $date_to,
      '#attributes' => ['name' => 'date_to'],
    ];

    $form['room'] = [
      '#type' => 'select',
      '#title' => $this->t('Номер'),
      '#options' => $room_options,
      '#default_value' => $room,
      '#attributes' => ['name' => 'room'],
    ];

    $form['buttons'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['reservation-filter-actions']],
    ];

    $form['buttons']['submit'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => '🔍',
      '#attributes' => [
        'type' => 'submit',
        'title' => (string) $this->t('Фильтр'),
        'class' => ['button', 'button--primary'],
      ],
    ];

    $form['buttons']['reset'] = [
      '#type' => 'link',
      '#title' => '↺',
      '#url' => Url::fromRoute('entity.hr_reservation.collection'),
      '#attributes' => [
        'class' => ['button'],
        'title' => (string) $this->t('Сброс'),
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

    $query->sort('check_in', 'DESC');
    $query->pager($this->limit);

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['guest_name'] = [
      'data' => $this->t('Гость'),
      'field' => 'guest_name',
      'spec' => [
        'column' => 'guest_name',
      ],
      'sort' => 'asc',
    ];
    $header['room'] = $this->t('Номер');
    $header['check_in'] = [
      'data' => $this->t('Заезд'),
      'field' => 'check_in',
      'spec' => [
        'column' => 'check_in',
      ],
    ];
    $header['check_out'] = [
      'data' => $this->t('Выезд'),
      'field' => 'check_out',
      'spec' => [
        'column' => 'check_out',
      ],
    ];
    $header['status'] = [
      'data' => $this->t('Статус'),
      'field' => 'status',
      'spec' => [
        'column' => 'status',
      ],
    ];
    $header['total_price'] = [
      'data' => $this->t('Итого'),
      'field' => 'total_price',
      'spec' => [
        'column' => 'total_price',
      ],
    ];
    $header['operations'] = $this->t('Действия');
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

    // Room name. Link to edit form only for managers; clients see text.
    $room = $entity->get('room_id')->entity;
    if ($room) {
      if (\Drupal::currentUser()->hasPermission('administer hotel reservation')) {
        $row['room']['data'] = [
          '#type' => 'link',
          '#title' => $room->label(),
          '#url' => $room->toUrl('edit-form'),
        ];
      }
      else {
        $row['room'] = $room->label();
      }
    }
    else {
      $row['room'] = $this->t('—');
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

    // Operations: edit/delete for full admins and the hotel_owner role,
    // status changes also for users with the status permission.
    $account = \Drupal::currentUser();
    $can_manage = $account->hasPermission('administer hotel reservation')
      || in_array('hotel_owner', $account->getRoles(), TRUE);
    $operations = [];
    if ($can_manage) {
      $operations['edit'] = [
        'title' => $this->t('Изменить'),
        'url' => $entity->toUrl('edit-form'),
      ];
      $operations['delete'] = [
        'title' => $this->t('Удалить'),
        'url' => $entity->toUrl('delete-form'),
      ];
    }

    // Add quick status change links (CSRF token added explicitly because
    // operations links are rendered without Link element processing).
    if (\Drupal::currentUser()->hasPermission('administer hotel reservation') || \Drupal::currentUser()->hasPermission('update hotel reservation status')) {
      $status_transitions = $this->getStatusTransitions($status_value);
      foreach ($status_transitions as $transition_status => $transition_label) {
        $status_url = Url::fromRoute('hotel_reservation.reservation_status', [
          'hr_reservation' => $entity->id(),
          'status' => $transition_status,
        ]);
        $status_url->setOption('query', [
          'token' => \Drupal::csrfToken()->get($status_url->getInternalPath()),
        ]);
        $operations['status_' . $transition_status] = [
          'title' => $transition_label,
          'url' => $status_url,
        ];
      }
    }

    if (!empty($operations)) {
      // Inline buttons instead of the operations dropbutton: the dropbutton
      // widget is unstyled/broken in the frontend theme. Access was already
      // checked above; links render without an extra access check so CSRF
      // status links are never hidden by mistake.
      $buttons = [];
      foreach ($operations as $key => $operation) {
        try {
          $url_string = $operation['url']->toString();
        }
        catch (\Throwable $e) {
          continue;
        }
        $class = ['button', 'button--small', 'hr-op-button'];
        if (strpos($key, 'status_') === 0) {
          $class[] = 'hr-op-button--status';
          $target_map = [
            'confirmed' => 'hr-op-button--confirm',
            'cancelled' => 'hr-op-button--cancel',
            'checked_in' => 'hr-op-button--checkin',
            'checked_out' => 'hr-op-button--checkout',
          ];
          $target = substr($key, 7);
          if (isset($target_map[$target])) {
            $class[] = $target_map[$target];
          }
        }
        elseif ($key === 'edit') {
          $class[] = 'hr-op-button--edit';
        }
        elseif ($key === 'delete') {
          $class[] = 'hr-op-button--delete';
        }
        $icon_map = [
          'edit' => '✎',
          'delete' => '🗑',
          'status_confirmed' => '✓',
          'status_cancelled' => '✕',
          'status_checked_in' => '🔑',
          'status_checked_out' => '🚪',
        ];
        $buttons[$key] = [
          '#type' => 'inline_template',
          '#template' => '<a href="{{ url }}" class="{{ classes }}" title="{{ title }}"><span class="hr-op-button__icon">{{ icon }}</span><span class="hr-op-button__text">{{ title }}</span></a>',
          '#context' => [
            'url' => $url_string,
            'classes' => implode(' ', $class),
            'title' => (string) $operation['title'],
            'icon' => $icon_map[$key] ?? (string) $operation['title'],
          ],
        ];
      }
      if (!empty($buttons)) {
        $row['operations']['data'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['hr-res-ops']],
          'buttons' => $buttons,
        ];
      }
      else {
        $row['operations'] = '';
      }
    }
    else {
      $row['operations'] = '';
    }

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
        'confirmed' => $this->t('Подтвердить'),
        'cancelled' => $this->t('Отменить'),
      ],
      'confirmed' => [
        'checked_in' => $this->t('Заселить'),
        'cancelled' => $this->t('Отменить'),
      ],
      'checked_in' => [
        'checked_out' => $this->t('Выселить'),
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
    $build['title'] = [
      '#markup' => '<h1 class="hr-admin-page-title">' . $this->t('Бронирования') . '</h1>',
      '#weight' => -100,
    ];
    $account = \Drupal::currentUser();
    if ($account->hasPermission('administer hotel reservation')
      || in_array('hotel_owner', $account->getRoles(), TRUE)) {
      // Plain path link on purpose: no route lookup, always renders when
      // the user is allowed to add bookings.
      $build['add_button'] = [
        '#type' => 'inline_template',
        '#template' => '<a href="/admin/hotel-reservation/reservations/add" class="button button--primary hr-reservations-list__add">{{ title }}</a>',
        '#context' => ['title' => $this->t('＋ Добавить бронь')],
        '#weight' => -90,
      ];
    }
    $build['filter'] = $this->buildFilterForm();

    // Build export URL with current filter params preserved.
    $export_params = [];
    $status = $this->request->query->get('status', '');
    if ($status !== '') {
      $export_params['status'] = $status;
    }
    $date_from = $this->request->query->get('date_from', '');
    if ($date_from !== '') {
      $export_params['date_from'] = $date_from;
    }
    $date_to = $this->request->query->get('date_to', '');
    if ($date_to !== '') {
      $export_params['date_to'] = $date_to;
    }
    $room = $this->request->query->get('room', '');
    if ($room !== '') {
      $export_params['room'] = $room;
    }

    $export_url = Url::fromRoute('hotel_reservation.export_csv');
    if (!empty($export_params)) {
      $export_url->setOption('query', $export_params);
    }

    $build['filter']['buttons']['export'] = [
      '#type' => 'link',
      '#title' => '📥',
      '#url' => $export_url,
      '#attributes' => [
        'class' => ['button', 'button--primary'],
        'title' => (string) $this->t('Экспорт CSV'),
      ],
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#rows' => [],
      '#empty' => $this->t('Бронирований не найдено.'),
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

    $build['#prefix'] = '<div class="wrapper"><div class="hr-reservations-list">';
    $build['#suffix'] = '</div></div>';

    return $build;
  }

}
