<?php

namespace Drupal\hotel_reservation\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Room entity.
 *
 * @ingroup hotel_reservation
 *
 * @ContentEntityType(
 *   id = "hr_room",
 *   label = @Translation("Room"),
 *   label_collection = @Translation("Rooms"),
 *   label_singular = @Translation("room"),
 *   label_plural = @Translation("rooms"),
 *   handlers = {
 *     "list_builder" = "Drupal\hotel_reservation\RoomListBuilder",
 *     "form" = {
 *       "default" = "Drupal\hotel_reservation\Form\RoomForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 *   base_table = "hr_room",
 *   admin_permission = "administer hotel reservation",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "published" = "status",
 *   },
 *   links = {
 *     "collection" = "/admin/hotel-reservation/rooms",
 *     "add-form" = "/admin/hotel-reservation/rooms/add",
 *     "edit-form" = "/admin/hotel-reservation/rooms/{hr_room}/edit",
 *     "delete-form" = "/admin/hotel-reservation/rooms/{hr_room}/delete",
 *   },
 * )
 */
class Room extends ContentEntityBase {

  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->get('name')->value ?? '';
  }

  /**
   * Gets the room name.
   *
   * @return string
   *   The room name.
   */
  public function getName(): string {
    return $this->get('name')->value ?? '';
  }

  /**
   * Sets the room name.
   *
   * @param string $name
   *   The room name.
   *
   * @return $this
   */
  public function setName(string $name): self {
    $this->set('name', $name);
    return $this;
  }

  /**
   * Gets the room description.
   *
   * @return string|null
   *   The room description or NULL if empty.
   */
  public function getDescription(): ?string {
    return $this->get('description')->value ?? NULL;
  }

  /**
   * Sets the room description.
   *
   * @param string|null $description
   *   The room description.
   *
   * @return $this
   */
  public function setDescription(?string $description): self {
    $this->set('description', $description);
    return $this;
  }

  /**
   * Gets the room capacity.
   *
   * @return int
   *   The room capacity.
   */
  public function getCapacity(): int {
    return (int) $this->get('capacity')->value;
  }

  /**
   * Sets the room capacity.
   *
   * @param int $capacity
   *   The room capacity.
   *
   * @return $this
   */
  public function setCapacity(int $capacity): self {
    $this->set('capacity', $capacity);
    return $this;
  }

  /**
   * Gets the base price per night.
   *
   * @return string
   *   The base price as a decimal string.
   */
  public function getBasePrice(): string {
    return $this->get('base_price')->value ?? '0.00';
  }

  /**
   * Sets the base price per night.
   *
   * @param string $price
   *   The base price.
   *
   * @return $this
   */
  public function setBasePrice(string $price): self {
    $this->set('base_price', $price);
    return $this;
  }

  /**
   * Gets the amenities list.
   *
   * @return string
   *   Comma-separated amenity names.
   */
  public function getAmenities(): string {
    return $this->get('amenities')->value ?? '';
  }

  /**
   * Sets the amenities list.
   *
   * @param string $amenities
   *   Comma-separated amenity names.
   *
   * @return $this
   */
  public function setAmenities(string $amenities): self {
    $this->set('amenities', $amenities);
    return $this;
  }

  /**
   * Gets the sort weight.
   *
   * @return int
   *   The sort weight.
   */
  public function getSortWeight(): int {
    return (int) $this->get('sort_weight')->value;
  }

  /**
   * Sets the sort weight.
   *
   * @param int $weight
   *   The sort weight.
   *
   * @return $this
   */
  public function setSortWeight(int $weight): self {
    $this->set('sort_weight', $weight);
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
   * Sets the creation timestamp.
   *
   * @param int $timestamp
   *   The creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime(int $timestamp): self {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setReadOnly(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Room Name'))
      ->setDescription(t('The name of the room.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDescription(t('A detailed description of the room.'))
      ->setDefaultValue('')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 0,
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['room_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Room Type'))
      ->setDescription(t('The category or type of the room.'))
      ->setSettings([
        'allowed_values' => [
          'standard' => 'Standard',
          'superior' => 'Superior',
          'deluxe' => 'Deluxe',
          'suite' => 'Suite',
          'apartment' => 'Apartment',
          'villa' => 'Villa',
          'family' => 'Family',
          'economy' => 'Economy',
        ],
        'allowed_values_function' => '',
      ])
      ->setDefaultValue('standard')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['capacity'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Capacity'))
      ->setDescription(t('The maximum number of guests the room can accommodate.'))
      ->setSetting('min', 1)
      ->setSetting('unsigned', TRUE)
      ->setDefaultValue(2)
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['base_price'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Base Price per Night'))
      ->setDescription(t('The base price per night in the default currency.'))
      ->setSettings([
        'precision' => 10,
        'scale' => 2,
      ])
      ->setDefaultValue('0.00')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_decimal',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 2,
        'settings' => [
          'precision' => 10,
          'scale' => 2,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['amenities'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Amenities'))
      ->setDescription(t('A comma-separated list of amenity names (e.g. WiFi, TV, Minibar).'))
      ->setDefaultValue('')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => 3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 3,
        'settings' => [
          'rows' => 3,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Published'))
      ->setDescription(t('Whether the room is published and available for booking.'))
      ->setDefaultValue(TRUE)
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 4,
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['sort_weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Sort Weight'))
      ->setDescription(t('The weight of this room for ordering in lists. Lower values appear first.'))
      ->setDefaultValue(0)
      ->setSetting('unsigned', FALSE)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the room was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the room was last edited.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
