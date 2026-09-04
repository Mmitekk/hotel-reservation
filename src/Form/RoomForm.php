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
      'amenities' => -1,
      'sort_weight' => 0,
      'status' => 1,
    ];

    foreach ($field_order as $field_name => $weight) {
      if (isset($form[$field_name])) {
        $form[$field_name]['#weight'] = $weight;
      }
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
