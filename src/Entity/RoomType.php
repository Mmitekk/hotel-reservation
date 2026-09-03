<?php

namespace Drupal\hotel_reservation\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Room Type entity.
 *
 * @ConfigEntityType(
 *   id = "hr_room_type",
 *   label = @Translation("Тип номера"),
 *   label_collection = @Translation("Типы номеров"),
 *   label_singular = @Translation("тип номера"),
 *   label_plural = @Translation("типы номеров"),
 *   handlers = {
 *     "list_builder" = "Drupal\hotel_reservation\RoomTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\hotel_reservation\Form\RoomTypeForm",
 *       "edit" = "Drupal\hotel_reservation\Form\RoomTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   config_prefix = "hr_room_type",
 *   admin_permission = "administer hotel reservation",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "color",
 *     "weight",
 *     "uuid"
 *   },
 *   links = {
 *     "collection" = "/admin/hotel-reservation/room-types",
 *     "add-form" = "/admin/hotel-reservation/room-types/add",
 *     "edit-form" = "/admin/hotel-reservation/room-types/{hr_room_type}/edit",
 *     "delete-form" = "/admin/hotel-reservation/room-types/{hr_room_type}/delete"
 *   }
 * )
 */
class RoomType extends ConfigEntityBase {

  /**
   * Machine name.
   *
   * @var string
   */
  protected $id;

  /**
   * Human-readable label.
   *
   * @var string
   */
  protected $label;

  /**
   * Accent color.
   *
   * @var string
   */
  protected $color = '#6b7280';

  /**
   * Sort weight.
   *
   * @var int
   */
  protected $weight = 0;

  public function getColor(): string {
    return $this->color ?? '#6b7280';
  }

  public function setColor(string $color): self {
    $this->color = $color;
    return $this;
  }

  public function getWeight(): int {
    return (int) ($this->weight ?? 0);
  }

  public function setWeight(int $weight): self {
    $this->weight = $weight;
    return $this;
  }

}
