<?php

namespace Drupal\hotel_reservation\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for the hr_room entity.
 *
 * @ingroup hotel_reservation
 */
class RoomForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Adjust the capacity field to enforce 1-20 range.
    if (isset($form['capacity']['widget'][0]['value'])) {
      $form['capacity']['widget'][0]['value']['#min'] = 1;
      $form['capacity']['widget'][0]['value']['#max'] = 20;
      $form['capacity']['widget'][0]['value']['#description'] = $this->t('Количество гостей в номере (1–20).');
    }

    // Add help text to the amenities field.
    if (isset($form['amenities']['widget'][0]['value'])) {
      $form['amenities']['widget'][0]['value']['#description'] = $this->t(
        'Введите список удобств через запятую. Например: Спа, Wi-Fi, ТВ, Парковка, Минибар, Сейф, Кондиционер'
      );
    }

    // Group fields into logical fieldsets for a better UX.
    $field_order = [
      'name' => -5,
      'image' => -4.5,
      'images' => -4.4,
      'teaser' => -4.2,
      'description' => -4,
      'capacity' => -3,
      'base_price' => -2,
      'price_mon' => -1.9,
      'price_tue' => -1.8,
      'price_wed' => -1.7,
      'price_thu' => -1.6,
      'price_fri' => -1.5,
      'price_sat' => -1.4,
      'price_sun' => -1.3,
      'amenities' => -1,
      'sort_weight' => 0,
      'status' => 1,
    ];

    foreach ($field_order as $field_name => $weight) {
      if (isset($form[$field_name])) {
        $form[$field_name]['#weight'] = $weight;
      }
    }

    // Group base price + weekday prices into one week-strip block.
    // Pure markup wrapper (#prefix/#suffix): element names and values
    // stay untouched, so entity mapping keeps working.
    $short_days = [
      'price_mon' => 'Пн',
      'price_tue' => 'Вт',
      'price_wed' => 'Ср',
      'price_thu' => 'Чт',
      'price_fri' => 'Пт',
      'price_sat' => 'Сб',
      'price_sun' => 'Вс',
    ];
    $groupable = isset($form['base_price']);
    foreach ($short_days as $field_name => $short) {
      if (!isset($form[$field_name]['widget'][0]['value'])) {
        $groupable = FALSE;
        break;
      }
    }
    if ($groupable) {
      foreach ($short_days as $field_name => $short) {
        $form[$field_name]['widget'][0]['value']['#title'] = $short;
        $form[$field_name]['widget'][0]['value']['#description'] = '';
      }
      $form['base_price']['#prefix'] = '<div class="hr-weekday-prices"><div class="hr-weekday-prices__title">' . $this->t('Цены по дням недели') . '</div><p class="hr-weekday-prices__hint">' . $this->t('Пустое поле — действует базовая цена.') . '</p><div class="hr-weekday-prices__grid">';
      $form['price_sun']['#suffix'] = '</div></div>';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $status = $entity->save();

    $name = $entity->label();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Номер «%name» создан.', ['%name' => $name]));
    }
    else {
      $this->messenger()->addStatus($this->t('Номер «%name» сохранён.', ['%name' => $name]));
    }

    $form_state->setRedirect('entity.hr_room.collection');
  }

}
