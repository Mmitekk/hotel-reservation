<?php

namespace Drupal\hotel_reservation\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;

/**
 * Provides a 'Hotel Reservation Booking Form' block.
 *
 * @Block(
 *   id = "hotel_reservation_booking_form",
 *   admin_label = @Translation("Форма бронирования отеля"),
 *   category = @Translation("Бронирование отеля"),
 * )
 */
class BookingFormBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'show_conditions' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form['show_conditions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Показывать условия бронирования'),
      '#description' => $this->t('Отображать условия бронирования из настроек модуля.'),
      '#default_value' => $config['show_conditions'] ?? TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['show_conditions'] = (bool) $form_state->getValue('show_conditions');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = \Drupal::config('hotel_reservation.settings');
    $block_config = $this->getConfiguration();
    $currency = $config->get('currency_symbol') ?: '₽';
    $min_stay = (int) $config->get('min_stay_nights') ?: 1;
    $max_stay = (int) $config->get('max_stay_nights') ?: 30;
    $check_in_time = $config->get('check_in_time') ?: '14:00';
    $check_out_time = $config->get('check_out_time') ?: '12:00';
    $booking_conditions = $config->get('booking_conditions') ?? '';
    $hotel_name = $config->get('hotel_name') ?: \Drupal::config('system.site')->get('name');

    // Design settings with defaults.
    $form_title = $config->get('form_title') ?: '';
    $form_subtitle = $config->get('form_subtitle') ?: '';
    $button_text = $config->get('form_button_text') ?: $this->t('Забронировать');
    $primary_color = $config->get('form_primary_color') ?: '#d97706';
    $bg_color = $config->get('form_background_color') ?: '#ffffff';
    $text_color = $config->get('form_text_color') ?: '#1a1a2e';
    $border_radius = (int) ($config->get('form_border_radius') ?: 10);
    $success_title = $config->get('form_success_title') ?: $this->t('Заявка отправлена!');
    $success_text = $config->get('form_success_text') ?: $this->t('Ваша заявка #@id ожидает подтверждения. Мы свяжемся с вами в ближайшее время.');

    $display_title = !empty($form_title) ? $form_title : $hotel_name;
    $display_subtitle = !empty($form_subtitle) ? $form_subtitle : $this->t('Заезд с @in, выезд до @out', [
      '@in' => $check_in_time,
      '@out' => $check_out_time,
    ]);

    $build = [];

    // Attach CSS/JS library.
    $build['#attached']['library'][] = 'hotel_reservation/booking-form';

    // Derive a slightly darker shade for input backgrounds.
    $bg_alt_color = $bg_color === '#ffffff' ? '#fafafa' : $bg_color;

    // CSS custom properties for theming.
    $build['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '.hr-booking-form{--hr-primary:' . $primary_color . ';--hr-bg:' . $bg_color . ';--hr-bg-alt:' . $bg_alt_color . ';--hr-text:' . $text_color . ';--hr-radius:' . $border_radius . 'px;}',
      ],
      'hr-form-design',
    ];

    // Pass settings to JS — ALL config keys including design settings.
    $build['#attached']['drupalSettings']['hotelReservation'] = [
      'currencySymbol' => $currency,
      'minStay' => $min_stay,
      'maxStay' => $max_stay,
      'checkInTime' => $check_in_time,
      'checkOutTime' => $check_out_time,
      'bookingConditions' => $booking_conditions,
      'apiCheckUrl' => Url::fromRoute('hotel_reservation.api_check_availability')->toString(),
      'apiSubmitUrl' => Url::fromRoute('hotel_reservation.api_submit_reservation')->toString(),
      'buttonText' => $button_text,
      'successTitle' => $success_title,
      'successText' => $success_text,
      'formPrimaryColor' => $primary_color,
      'formBackgroundColor' => $bg_color,
      'formTextColor' => $text_color,
      'formBorderRadius' => $border_radius,
    ];

    // Build HTML.
    $html = '<div class="hr-booking-form">';

    // Title.
    $html .= '<h2 class="hr-booking-form__title">' . Html::escape($display_title) . '</h2>';
    $html .= '<p class="hr-booking-form__subtitle">' . Html::escape($display_subtitle) . '</p>';

    // Step indicators.
    $html .= '<div class="hr-steps">';
    $html .= '<div class="hr-step active" data-step="search">';
    $html .= '<span class="hr-step-number">1</span>' . $this->t('Даты') . '</div>';
    $html .= '<div class="hr-step-connector"></div>';
    $html .= '<div class="hr-step" data-step="select">';
    $html .= '<span class="hr-step-number">2</span>' . $this->t('Номер') . '</div>';
    $html .= '<div class="hr-step-connector"></div>';
    $html .= '<div class="hr-step" data-step="book">';
    $html .= '<span class="hr-step-number">3</span>' . $this->t('Детали') . '</div>';
    $html .= '</div>';

    // === Section 1: Search ===
    $html .= '<div class="hr-section hr-section--search">';
    $html .= '<div class="hr-search-errors"></div>';

    // Date fields — TWO SEPARATE .hr-form-group divs (no flex row wrapper)
    $html .= '<div class="hr-form-group">';
    $html .= '<label for="hr-check-in">' . $this->t('Дата заезда') . '</label>';
    $html .= '<input type="datetime-local" id="hr-check-in" class="hr-field-check-in" required>';
    $html .= '</div>';
    $html .= '<div class="hr-form-group">';
    $html .= '<label for="hr-check-out">' . $this->t('Дата выезда') . '</label>';
    $html .= '<input type="datetime-local" id="hr-check-out" class="hr-field-check-out" required>';
    $html .= '</div>';

    $html .= '<div class="hr-form-group">';
    $html .= '<label>' . $this->t('Гости') . '</label>';
    $html .= '<div class="hr-guest-counter">';
    $html .= '<button type="button" class="hr-guest-counter__btn hr-guest-counter__btn--minus">−</button>';
    $html .= '<input type="number" class="hr-field-guests hr-guest-counter__value" value="1" min="1" max="20" readonly>';
    $html .= '<button type="button" class="hr-guest-counter__btn hr-guest-counter__btn--plus">+</button>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<button type="button" class="hr-btn hr-btn--primary hr-search-btn">' . $this->t('Найти свободные номера') . '</button>';
    $html .= '</div>';

    // === Section 2: Select Room ===
    $html .= '<div class="hr-section hr-section--select" style="display:none">';
    $html .= '<button type="button" class="hr-back-btn">← ' . $this->t('Изменить даты') . '</button>';
    $html .= '<div class="hr-results">';
    $html .= '<div class="hr-results__title"></div>';
    $html .= '<div class="hr-results-list"></div>';
    $html .= '</div>';
    $html .= '</div>';

    // === Section 3: Booking Details ===
    $html .= '<div class="hr-section hr-section--book" style="display:none">';
    $html .= '<button type="button" class="hr-back-btn">← ' . $this->t('Выбрать другой номер') . '</button>';
    $html .= '<div class="hr-book-errors"></div>';

    $html .= '<div class="hr-form-row">';
    $html .= '<div class="hr-form-group">';
    $html .= '<label>' . $this->t('Номер') . '</label>';
    $html .= '<div class="hr-room-selected-name">—</div>';
    $html .= '</div>';
    $html .= '<div class="hr-form-group">';
    $html .= '<label>' . $this->t('Итого') . '</label>';
    $html .= '<div class="hr-room-selected-price">0 ' . $currency . '</div>';
    $html .= '</div>';
    $html .= '</div>';

    // Price breakdown.
    $html .= '<div class="hr-price-breakdown" style="display:none">';
    $html .= '<div class="hr-price-breakdown__title">' . $this->t('Подробности цены') . '</div>';
    $html .= '<div class="hr-price-breakdown-body"></div>';
    $html .= '</div>';

    $html .= '<input type="hidden" class="hr-field-room-id">';

    $html .= '<div class="hr-form-row">';
    $html .= '<div class="hr-form-group">';
    $html .= '<label for="hr-guest-name">' . $this->t('ФИО') . ' *</label>';
    $html .= '<input type="text" id="hr-guest-name" class="hr-field-guest-name" placeholder="' . $this->t('Ваше имя') . '" required>';
    $html .= '</div>';
    $html .= '<div class="hr-form-group">';
    $html .= '<label for="hr-guest-phone">' . $this->t('Телефон') . ' *</label>';
    $html .= '<input type="tel" id="hr-guest-phone" class="hr-field-guest-phone" placeholder="+7 (___) ___-__-__" required>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div class="hr-form-group">';
    $html .= '<label for="hr-guest-email">' . $this->t('Email') . '</label>';
    $html .= '<input type="email" id="hr-guest-email" class="hr-field-guest-email" placeholder="' . $this->t('email@example.com') . '">';
    $html .= '</div>';

    $html .= '<div class="hr-form-group">';
    $html .= '<label for="hr-notes">' . $this->t('Заметки') . '</label>';
    $html .= '<textarea id="hr-notes" class="hr-field-notes" rows="2" placeholder="' . $this->t('Пожелания...') . '"></textarea>';
    $html .= '</div>';

    // Booking conditions.
    if (!empty($block_config['show_conditions']) && !empty($booking_conditions)) {
      $escaped_conditions = Html::escape($booking_conditions);
      $html .= '<div class="hr-terms">';
      $html .= '<div class="hr-terms__title">' . $this->t('Условия бронирования') . '</div>';
      $html .= '<div class="hr-terms__text">' . nl2br($escaped_conditions) . '</div>';
      $html .= '</div>';
    }

    $html .= '<button type="button" class="hr-btn hr-btn--primary hr-book-btn" data-btn-text="' . Html::escape($button_text) . '">' . Html::escape($button_text) . '</button>';
    $html .= '</div>';

    // === Section 4: Success ===
    $html .= '<div class="hr-section hr-section--success" style="display:none">';
    $html .= '<div class="hr-success">';
    $html .= '<div class="hr-success__icon">✓</div>';
    $html .= '<h3 class="hr-success__title">' . Html::escape($success_title) . '</h3>';
    $html .= '<p class="hr-success__text" data-template="' . Html::escape($success_text) . '">' . Html::escape($success_text) . '</p>';
    $html .= '<button type="button" class="hr-btn hr-btn--secondary hr-new-search-btn" style="margin-top:16px;">' . $this->t('Новое бронирование') . '</button>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '</div>';

    $build['content'] = [
      '#type' => 'markup',
      '#markup' => $html,
      '#allowed_tags' => [
        'div', 'h2', 'h3', 'h4', 'p', 'label', 'input', 'button', 'textarea',
        'span', 'small', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'br', 'a', 'strong', 'em', 'pre', 'style',
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['hotel_reservation_settings', 'hr_room_list'];
  }

}
