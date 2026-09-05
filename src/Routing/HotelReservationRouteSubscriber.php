<?php

namespace Drupal\hotel_reservation\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters entity collection routes for read-only client access.
 */
class HotelReservationRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // The reservations list is admin-only by default (admin_permission).
    // Relax it so the hotel_client role can view the list and entities.
    if ($route = $collection->get('entity.hr_reservation.collection')) {
      $requirements = $route->getRequirements();
      unset($requirements['_permission']);
      $requirements['_custom_access'] = '\Drupal\hotel_reservation\Access\HotelReservationAccessCheck::accessReservations';
      $route->setRequirements($requirements);
    }
  }

}
