<?php

namespace Drupal\hotel_reservation;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Entity access handler mapping granular hotel permissions.
 *
 * Replaces the blanket admin_permission gate so roles with granular
 * permissions (e.g. hotel_owner) can view/create/update/delete rooms and
 * reservations. This also unblocks integrations that check entity access,
 * such as the media library dialog on room image fields (its opener checks
 * update/create access on the host entity).
 */
class HotelReservationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * Operation permissions per entity type.
   *
   * @return array
   *   Entity type ID => operation => permission list (OR-combined with the
   *   full administer permission).
   */
  protected static function operationPermissions(): array {
    return [
      'hr_room' => [
        'view' => ['view hotel rooms'],
        'create' => ['create hotel rooms'],
        'update' => ['edit hotel rooms'],
        'delete' => ['delete hotel rooms'],
      ],
      'hr_reservation' => [
        'view' => ['view hotel reservations'],
        'create' => ['create hotel reservations'],
        'update' => ['edit hotel reservations'],
        'delete' => ['delete hotel reservations'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $map = static::operationPermissions()[$this->entityTypeId] ?? [];
    $permissions = array_merge(['administer hotel reservation'], $map[$operation] ?? []);
    return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $map = static::operationPermissions()[$this->entityTypeId] ?? [];
    $permissions = array_merge(['administer hotel reservation'], $map['create'] ?? []);
    return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
  }

}
