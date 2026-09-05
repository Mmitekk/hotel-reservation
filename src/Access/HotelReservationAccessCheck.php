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
   * Permissions granted to the hotel_client role.
   *
   * @return string[]
   *   Permission machine names.
   */
  public static function clientPermissions(): array {
    return [
      'access content',
      'access administration pages',
      'access toolbar',
      'view the administration theme',
      'view hotel reservation dashboard',
      'view hotel reservation analytics',
      'view hotel reservation calendar',
      'view hotel reservations',
    ];
  }

  /**
   * Combines full admin access with a read-only client permission.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param string $client_permission
   *   The read-only permission for the hotel_client role.
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

}
