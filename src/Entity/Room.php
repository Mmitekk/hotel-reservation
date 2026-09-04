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
 *   label = @Translation("Номер"),
 *   label_collection = @Translation("Номера"),
 *   label_singular = @Translation("номер"),
 *   label_plural = @Translation("номера"),
 *   handlers = {
 *     "list_builder" = "Drupal\hotel_reservation\RoomListBuilder",
 *     "form" = {
 *       "add" = "Drupal\hotel_reservation\Form\RoomForm",
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

  public function getTeaser(): ?string {
    return $this->get('teaser')->value ?? NULL;
  }

  public function setTeaser(?string $teaser): self {
    $this->set('teaser', $teaser);
    return $this;
  }

  public static function plainText(?string $raw): string {
    if ($raw === NULL || $raw === '') {
      return '';
    }
    $plain = trim(strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plain = preg_replace('/\s+/', ' ', $plain);
    return $plain;
  }

  public function getTeaserPlain(int $limit = 120): string {
    $plain = self::plainText($this->getTeaser() ?? '');
    if ($plain !== '') {
      return mb_strlen($plain) > $limit ? mb_substr($plain, 0, $limit) . '...' : $plain;
    }
    $fallback = self::plainText($this->getDescription() ?? '');
    return mb_strlen($fallback) > $limit ? mb_substr($fallback, 0, $limit) . '...' : $fallback;
  }

  public function getDescriptionPlain(): string {
    return self::plainText($this->getDescription() ?? '');
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
   * Gets the room image file (supports both media and legacy file).
   *
   * @return \Drupal\file\FileInterface|null
   */
  public function getImage(): ?\Drupal\file\FileInterface {
    $item = $this->get('image')->first();
    if (!$item || empty($item->target_id)) {
      return NULL;
    }
    $id = $item->target_id;
    try {
      $media = \Drupal::entityTypeManager()->getStorage('media')->load($id);
      if ($media && $media->bundle() === 'image' && $media->hasField('field_media_image')) {
        $f = $media->get('field_media_image')->first();
        if ($f && !empty($f->target_id)) {
          return \Drupal::entityTypeManager()->getStorage('file')->load($f->target_id);
        }
      }
    }
    catch (\Exception $e) {
    }
    return \Drupal::entityTypeManager()->getStorage('file')->load($id);
  }

  public function getImageMedia(): ?\Drupal\media\MediaInterface {
    $item = $this->get('image')->first();
    if (!$item || empty($item->target_id)) {
      return NULL;
    }
    try {
      $media = \Drupal::entityTypeManager()->getStorage('media')->load($item->target_id);
      if ($media && $media->bundle() === 'image') {
        return $media;
      }
    }
    catch (\Exception $e) {
    }
    return NULL;
  }

  public function getImageAlt(): string {
    $item = $this->get('image')->first();
    if (!$item || empty($item->target_id)) {
      return $this->getName();
    }
    if (!empty($item->alt)) {
      return $item->alt;
    }
    try {
      $media = \Drupal::entityTypeManager()->getStorage('media')->load($item->target_id);
      if ($media) {
        if ($media->hasField('field_media_image')) {
          $f = $media->get('field_media_image')->first();
          if ($f && !empty($f->alt)) {
            return $f->alt;
          }
        }
        return $media->label();
      }
    }
    catch (\Exception $e) {
    }
    return $this->getName();
  }

  /**
   * Gets the image URL or NULL.
   *
   * @return string|null
   */
  public function getImageUrl(): ?string {
    $file = $this->getImage();
    if (!$file) {
      return NULL;
    }
    try {
      return \Drupal::service('file_url_generator')->generateString($file->getFileUri());
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  public static function mediaImageData($mid): ?array {
    if (empty($mid)) {
      return NULL;
    }
    try {
      $media = \Drupal::entityTypeManager()->getStorage('media')->load($mid);
      if (!$media || $media->bundle() !== 'image' || !$media->hasField('field_media_image')) {
        return NULL;
      }
      $f = $media->get('field_media_image')->first();
      if (!$f || empty($f->target_id)) {
        return NULL;
      }
      $file = \Drupal::entityTypeManager()->getStorage('file')->load($f->target_id);
      if (!$file) {
        return NULL;
      }
      $url = \Drupal::service('file_url_generator')->generateString($file->getFileUri());
      $alt = !empty($f->alt) ? $f->alt : $media->label();
      return ['url' => $url, 'alt' => $alt, 'mid' => (int) $mid];
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  public function getGalleryIds(): array {
    $ids = [];
    try {
      foreach ($this->get('images') as $item) {
        if (!empty($item->target_id)) {
          $ids[] = (int) $item->target_id;
        }
      }
    }
    catch (\Exception $e) {
    }
    return $ids;
  }

  public function getSliderImages(): array {
    $slides = [];
    $seen = [];
    $preview = $this->getImageUrl();
    if ($preview) {
      $slides[] = ['url' => $preview, 'alt' => $this->getImageAlt()];
      foreach ($this->getGalleryIds() as $mid) {
        $d = self::mediaImageData($mid);
        if ($d && $d['url'] !== $preview) {
          $seen[$mid] = TRUE;
          $slides[] = $d;
        }
      }
      return $slides;
    }
    foreach ($this->getGalleryIds() as $mid) {
      if (!empty($seen[$mid])) {
        continue;
      }
      $d = self::mediaImageData($mid);
      if ($d) {
        $seen[$mid] = TRUE;
        $slides[] = $d;
      }
    }
    return $slides;
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

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Название номера'))
      ->setDescription(t('Название номера.'))
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

    $fields['image'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Изображение (превью)'))
      ->setDescription(t('Превью номера. Выберите из медиа-библиотеки.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setSettings([
        'target_type' => 'media',
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => ['image' => 'image'],
          'sort' => ['field' => '_none'],
          'auto_create' => FALSE,
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_label',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'media_library_widget',
        'weight' => -6,
        'settings' => [
          'media_types' => ['image'],
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['teaser'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Анонс (краткое описание)'))
      ->setDescription(t('Показывается в карточке «Наши номера». Если пусто — используется обрезанное основное описание.'))
      ->setDefaultValue('')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => -1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => -1,
        'settings' => [
          'rows' => 2,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['images'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Слайдер (изображения номера)'))
      ->setDescription(t('Дополнительные фото для слайдера в расширенной карточке. Первое фото превью подставляется автоматически.'))
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setSettings([
        'target_type' => 'media',
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => ['image' => 'image'],
          'sort' => ['field' => '_none'],
          'auto_create' => FALSE,
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_label',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'media_library_widget',
        'weight' => -5,
        'settings' => [
          'media_types' => ['image'],
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Описание (основное)'))
      ->setDescription(t('Полное описание номера для расширенной карточки (модальное окно).'))
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
      ->setLabel(t('Тип номера'))
      ->setDescription(t('Категория или тип номера.'))
      ->setSettings([
        'allowed_values' => [
          'standard' => 'Стандарт',
          'superior' => 'Супериор',
          'deluxe' => 'Делюкс',
          'suite' => 'Сьют',
          'apartment' => 'Апартаменты',
          'villa' => 'Вилла',
          'family' => 'Семейный',
          'economy' => 'Эконом',
        ],
        'allowed_values_function' => 'hotel_reservation_room_type_allowed_values',
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
      ->setLabel(t('Вместимость'))
      ->setDescription(t('Максимальное количество гостей в номере.'))
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
      ->setLabel(t('Базовая цена за ночь'))
      ->setDescription(t('Базовая цена за ночь в основной валюте.'))
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
      ->setLabel(t('Удобства'))
      ->setDescription(t('Список удобств через запятую (напр. Wi-Fi, ТВ, Минибар).'))
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
      ->setLabel(t('Опубликован'))
      ->setDescription(t('Опубликован ли номер и доступен для бронирования.'))
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
      ->setLabel(t('Вес для сортировки'))
      ->setDescription(t('Вес номера для сортировки в списках. Меньшие значения отображаются первыми.'))
      ->setDefaultValue(0)
      ->setSetting('unsigned', FALSE)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Создано'))
      ->setDescription(t('Время создания номера.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Изменено'))
      ->setDescription(t('Время последнего редактирования номера.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
