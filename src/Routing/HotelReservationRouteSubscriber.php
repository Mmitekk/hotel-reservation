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
    // Relax it so the hotel_owner role can view the list and entities.
    if ($route = $collection->get('entity.hr_reservation.collection')) {
      $requirements = $route->getRequirements();
      unset($requirements['_permission']);
      $requirements['_custom_access'] = '\Drupal\hotel_reservation\Access\HotelReservationAccessCheck::accessReservations';
      $route->setRequirements($requirements);
    }
    // Single reservation view: owner needs it (list links, post-create
    // redirect), guests never reach it (no links, uid-scoped page instead).
    if ($route = $collection->get('entity.hr_reservation.canonical')) {
      $requirements = $route->getRequirements();
      unset($requirements['_permission'], $requirements['_entity_access']);
      $requirements['_custom_access'] = '\Drupal\hotel_reservation\Access\HotelReservationAccessCheck::accessReservations';
      $route->setRequirements($requirements);
      $route->setDefault('_controller', '\Drupal\hotel_reservation\Controller\HotelReservationController::viewReservation');
    }
    // Granular admin permissions (OR with full administer access): separate
    // checkboxes, no role magic. '+' means OR, ',' would mean AND.
    if ($route = $collection->get('entity.hr_reservation.edit-form')) {
      $requirements = $route->getRequirements();
      unset($requirements['_permission'], $requirements['_entity_access']);
      $requirements['_permission'] = 'administer hotel reservation+edit hotel reservations';
      $route->setRequirements($requirements);
    }
    if ($route = $collection->get('entity.hr_reservation.add-form')) {
      $requirements = $route->getRequirements();
      unset($requirements['_permission'], $requirements['_entity_access'], $requirements['_entity_create_access']);
      $requirements['_permission'] = 'administer hotel reservation+create hotel reservations';
      $route->setRequirements($requirements);
    }
    if ($route = $collection->get('entity.hr_reservation.delete-form')) {
      $requirements = $route->getRequirements();
      unset($requirements['_permission'], $requirements['_entity_access']);
      $requirements['_permission'] = 'administer hotel reservation+delete hotel reservations';
      $route->setRequirements($requirements);
    }
  }

}
