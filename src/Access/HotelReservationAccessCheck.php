<?php

namespace Drupal\hotel_reservation\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Grants admins full access and clients read-only access.
 */
class HotelReservationAccessCheck {

  /**
   * Permissions granted to the hotel_owner role.
   *
   * No admin entry permissions (toolbar, administration pages, admin
   * theme): the owner works only on module pages via direct links.
   *
   * @return string[]
   *   Permission machine names.
   */
  public static function clientPermissions(): array {
    return [
      'access content',
      'view hotel reservation dashboard',
      'view hotel reservation analytics',
      'view hotel reservation calendar',
      'view hotel reservations',
      'view hotel rooms',
      'create hotel rooms',
      'edit hotel rooms',
      'delete hotel rooms',
      'create hotel reservations',
      'edit hotel reservations',
      'delete hotel reservations',
      'update hotel reservation status',
      'view media',
      'create media',
      'edit own media',
      'edit any media',
      'delete own media',
      'delete any media',
      'create image media',
      'edit own image media',
      'edit any image media',
      'delete own image media',
      'delete any image media',
      'view files',
    ];
  }

  /**
   * Admin entry permissions, revoked from both hotel roles.
   *
   * @return string[]
   *   Permission machine names.
   */
  public static function revokedAdminPermissions(): array {
    return [
      'access administration pages',
      'access toolbar',
      'view the administration theme',
    ];
  }

  /**
   * Permissions granted to the hotel_client (guest) role.
   *
   * @return string[]
   *   Permission machine names.
   */
  public static function guestPermissions(): array {
    return [
      'access content',
      'view own hotel reservations',
    ];
  }

  /**
   * Combines full admin access with a read-only client permission.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param string $client_permission
   *   The read-only permission for the hotel_owner role.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function adminOrClient(AccountInterface $account, string $client_permission): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'administer hotel reservation')
      ->orIf(AccessResult::allowedIfHasPermission($account, $client_permission));
  }

  /**
   * Checks access to the dashboard page.
   */
  public function accessDashboard(AccountInterface $account): AccessResultInterface {
    return $this->adminOrClient($account, 'view hotel reservation dashboard');
  }

  /**
   * Checks access to the analytics page.
   */
  public function accessAnalytics(AccountInterface $account): AccessResultInterface {
    return $this->adminOrClient($account, 'view hotel reservation analytics');
  }

  /**
   * Checks access to the calendar page.
   */
  public function accessCalendar(AccountInterface $account): AccessResultInterface {
    return $this->adminOrClient($account, 'view hotel reservation calendar');
  }

  /**
   * Checks access to the reservations list (entity.hr_reservation.collection).
   */
  public function accessReservations(AccountInterface $account): AccessResultInterface {
    return $this->adminOrClient($account, 'view hotel reservations');
  }

  /**
   * Checks access to reservation status changes.
   */
  public function accessReservationStatus(AccountInterface $account): AccessResultInterface {
    return $this->adminOrClient($account, 'update hotel reservation status');
  }

}
