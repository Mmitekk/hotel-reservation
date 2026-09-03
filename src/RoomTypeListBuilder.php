<?php

namespace Drupal\hotel_reservation;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

class RoomTypeListBuilder extends ConfigEntityListBuilder {

  public function buildHeader() {
    $header['label'] = $this->t('Название');
    $header['id'] = $this->t('Машинное имя');
    $header['color'] = $this->t('Цвет');
    $header['weight'] = $this->t('Вес');
    return $header + parent::buildHeader();
  }

  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\hotel_reservation\Entity\RoomType $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $color = $entity->getColor();
    $row['color']['data'] = [
      '#markup' => '<span style="display:inline-block;width:20px;height:20px;border-radius:50%;background:' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . ';border:1px solid #e5e7eb;vertical-align:middle;"></span> ' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8'),
    ];
    $row['weight'] = $entity->getWeight();
    return $row + parent::buildRow($entity);
  }

}
