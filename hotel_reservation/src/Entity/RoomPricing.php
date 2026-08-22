<?php

namespace Drupal\hotel_reservation\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Room Pricing entity for per-date custom prices.
 *
 * @ingroup hotel_reservation
 *
 * @ContentEntityType(
 *   id = "hr_room_pricing",
 *   label = @Translation("Room Pricing"),
 *   label_collection = @Translation("Room Pricings"),
 *   label_singular = @Translation("room pricing"),
 *   label_plural = @Translation("room pricings"),
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\EntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 *   base_table = "hr_room_pricing",
 *   admin_permission = "administer hotel reservation",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "edit-form" = "/admin/hotel-reservation/room-pricing/{hr_room_pricing}/edit",
 *     "delete-form" = "/admin/hotel-reservation/room-pricing/{hr_room_pricing}/delete",
 *   },
 * )
 */
class RoomPricing extends ContentEntityBase {

  /**
   * Gets the referenced room entity.
   *
   * @return \Drupal\hotel_reservation\Entity\Room|null
   *   The room entity or NULL.
   */
  public function getRoom(): ?Room {
    $room = $this->get('room_id')->entity;
    return $room instanceof Room ? $room : NULL;
  }

  /**
   * Gets the pricing date.
   *
   * @return string|null
   *   The date string in Y-m-d format or NULL.
   */
  public function getDate(): ?string {
    return $this->get('date')->value ?? NULL;
  }

  /**
   * Gets the price.
   *
   * @return string
   *   The price as a decimal string.
   */
  public function getPrice(): string {
    return $this->get('price')->value ?? '0.00';
  }

  /**
   * Sets the price.
   *
   * @param string $price
   *   The price.
   *
   * @return $this
   */
  public function setPrice(string $price): self {
    $this->set('price', $price);
    return $this;
  }

  /**
   * Gets the creation timestamp.
   *
   * @return int
   *   The creation timestamp.
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setReadOnly(TRUE);

    $fields['room_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Room'))
      ->setDescription(t('The room this pricing applies to.'))
      ->setSetting('target_type', 'hr_room')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -4,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Date'))
      ->setDescription(t('The date this pricing applies to.'))
      ->setRequired(TRUE)
      ->setSettings([
        'datetime_type' => 'date',
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'weight' => -3,
        'settings' => [
          'format_type' => 'medium',
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_datelist',
        'weight' => -3,
        'settings' => [
          'date_order' => 'DMY',
          'time_type' => 'none',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['price'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Price per Night'))
      ->setDescription(t('The custom price for this room on this date.'))
      ->setSettings([
        'precision' => 10,
        'scale' => 2,
      ])
      ->setDefaultValue('0.00')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_decimal',
        'weight' => -2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -2,
        'settings' => [
          'precision' => 10,
          'scale' => 2,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the pricing entry was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
