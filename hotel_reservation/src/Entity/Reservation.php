<?php

namespace Drupal\hotel_reservation\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Reservation entity.
 *
 * @ingroup hotel_reservation
 *
 * @ContentEntityType(
 *   id = "hr_reservation",
 *   label = @Translation("Reservation"),
 *   label_collection = @Translation("Reservations"),
 *   label_singular = @Translation("reservation"),
 *   label_plural = @Translation("reservations"),
 *   handlers = {
 *     "list_builder" = "Drupal\hotel_reservation\ReservationListBuilder",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "form" = {
 *       "default" = "Drupal\hotel_reservation\Form\ReservationForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 *   base_table = "hr_reservation",
 *   admin_permission = "administer hotel reservation",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "guest_name",
 *   },
 *   links = {
 *     "collection" = "/admin/hotel-reservation/reservations",
 *     "canonical" = "/admin/hotel-reservation/reservations/{hr_reservation}",
 *     "edit-form" = "/admin/hotel-reservation/reservations/{hr_reservation}/edit",
 *     "delete-form" = "/admin/hotel-reservation/reservations/{hr_reservation}/delete",
 *   },
 * )
 */
class Reservation extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * Status constant: pending.
   */
  const STATUS_PENDING = 'pending';

  /**
   * Status constant: confirmed.
   */
  const STATUS_CONFIRMED = 'confirmed';

  /**
   * Status constant: checked in.
   */
  const STATUS_CHECKED_IN = 'checked_in';

  /**
   * Status constant: checked out.
   */
  const STATUS_CHECKED_OUT = 'checked_out';

  /**
   * Status constant: cancelled.
   */
  const STATUS_CANCELLED = 'cancelled';

  /**
   * Status constant: expired.
   */
  const STATUS_EXPIRED = 'expired';

  /**
   * Gets the human-readable status label.
   *
   * @return string
   *   The status label.
   */
  public function getStatusLabel(): string {
    $labels = static::getStatusOptions();
    return $labels[$this->get('status')->value] ?? $this->get('status')->value;
  }

  /**
   * Returns an array of all status options.
   *
   * @return array
   *   An associative array of status labels keyed by status value.
   */
  public static function getStatusOptions(): array {
    return [
      self::STATUS_PENDING => t('Pending')->__toString(),
      self::STATUS_CONFIRMED => t('Confirmed')->__toString(),
      self::STATUS_CHECKED_IN => t('Checked in')->__toString(),
      self::STATUS_CHECKED_OUT => t('Checked out')->__toString(),
      self::STATUS_CANCELLED => t('Cancelled')->__toString(),
      self::STATUS_EXPIRED => t('Expired')->__toString(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $name = $this->get('guest_name')->value;
    if ($name) {
      return t('Reservation for @guest', ['@guest' => $name])->__toString();
    }
    return parent::label();
  }

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
   * Gets the check-in date as a DrupalDateTime object.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   The check-in date or NULL.
   */
  public function getCheckInDate(): ?\Drupal\Core\Datetime\DrupalDateTime {
    $value = $this->get('check_in')->value;
    if ($value) {
      return new \Drupal\Core\Datetime\DrupalDateTime($value);
    }
    return NULL;
  }

  /**
   * Gets the check-out date as a DrupalDateTime object.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   The check-out date or NULL.
   */
  public function getCheckOutDate(): ?\Drupal\Core\Datetime\DrupalDateTime {
    $value = $this->get('check_out')->value;
    if ($value) {
      return new \Drupal\Core\Datetime\DrupalDateTime($value);
    }
    return NULL;
  }

  /**
   * Gets the total price.
   *
   * @return string
   *   The total price as a decimal string.
   */
  public function getTotalPrice(): string {
    return $this->get('total_price')->value ?? '0.00';
  }

  /**
   * Sets the total price.
   *
   * @param string $price
   *   The total price.
   *
   * @return $this
   */
  public function setTotalPrice(string $price): self {
    $this->set('total_price', $price);
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

    $fields['room_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Room'))
      ->setDescription(t('The room this reservation is for.'))
      ->setSetting('target_type', 'hr_room')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -6,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['check_in'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Check-in Date'))
      ->setDescription(t('The date the guest will check in.'))
      ->setRequired(TRUE)
      ->setSettings([
        'datetime_type' => 'date',
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'weight' => -5,
        'settings' => [
          'format_type' => 'medium',
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_datelist',
        'weight' => -5,
        'settings' => [
          'date_order' => 'DMY',
          'time_type' => 'none',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['check_out'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Check-out Date'))
      ->setDescription(t('The date the guest will check out.'))
      ->setRequired(TRUE)
      ->setSettings([
        'datetime_type' => 'date',
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'weight' => -4,
        'settings' => [
          'format_type' => 'medium',
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_datelist',
        'weight' => -4,
        'settings' => [
          'date_order' => 'DMY',
          'time_type' => 'none',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['guest_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Guest Name'))
      ->setDescription(t('The full name of the guest.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['guest_phone'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Guest Phone'))
      ->setDescription(t('The phone number of the guest.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['guest_email'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Guest Email'))
      ->setDescription(t('The email address of the guest.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'email',
        'weight' => -1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['guest_count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Guest Count'))
      ->setDescription(t('The number of guests.'))
      ->setSetting('min', 1)
      ->setSetting('unsigned', TRUE)
      ->setDefaultValue(1)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The current status of the reservation.'))
      ->setSettings([
        'max_length' => 32,
        'allowed_values' => [
          'pending' => 'Pending',
          'confirmed' => 'Confirmed',
          'checked_in' => 'Checked in',
          'checked_out' => 'Checked out',
          'cancelled' => 'Cancelled',
          'expired' => 'Expired',
        ],
        'allowed_values_function' => '',
      ])
      ->setDefaultValue('pending')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['total_price'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Total Price'))
      ->setDescription(t('The total price for the entire reservation.'))
      ->setSettings([
        'precision' => 10,
        'scale' => 2,
      ])
      ->setDefaultValue('0.00')
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

    $fields['notes'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Guest Notes'))
      ->setDescription(t('Notes or comments from the guest.'))
      ->setDefaultValue('')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 3,
        'settings' => [
          'rows' => 3,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['admin_notes'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Admin Notes'))
      ->setDescription(t('Internal notes visible only to administrators.'))
      ->setDefaultValue('')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 4,
        'settings' => [
          'rows' => 3,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the reservation was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the reservation was last edited.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
