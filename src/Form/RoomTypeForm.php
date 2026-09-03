<?php

namespace Drupal\hotel_reservation\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

class RoomTypeForm extends EntityForm {

  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\hotel_reservation\Entity\RoomType $entity */
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Название'),
      '#default_value' => $entity->label(),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\hotel_reservation\Entity\RoomType::load',
      ],
      '#disabled' => !$entity->isNew(),
      '#required' => TRUE,
    ];

    $form['color'] = [
      '#type' => 'color',
      '#title' => $this->t('Цвет'),
      '#default_value' => $entity->getColor() ?: '#6b7280',
      '#required' => TRUE,
    ];

    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Вес'),
      '#description' => $this->t('Меньший вес — выше в списках.'),
      '#default_value' => $entity->getWeight(),
      '#required' => TRUE,
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $color = $form_state->getValue('color');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
      $form_state->setErrorByName('color', $this->t('Цвет должен быть в формате #RRGGBB.'));
    }
    if (!preg_match('/^[a-z0-9_]+$/', $form_state->getValue('id'))) {
      $form_state->setErrorByName('id', $this->t('Машинное имя — только a-z, 0-9, _.'));
    }
  }

  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();
    if ($status) {
      $this->messenger()->addStatus($this->t('Тип номера %label сохранён.', ['%label' => $entity->label()]));
    }
    $form_state->setRedirectUrl($entity->toUrl('collection'));
  }

}
