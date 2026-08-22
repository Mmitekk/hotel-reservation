<?php

namespace Drupal\hotel_reservation\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;

/**
 * Provides a 'Hotel Reservation Booking Form' block.
 *
 * This block renders a modern 3-step AJAX booking form that replaces
 * the old .front-form-block Webform.
 *
 * @Block(
 *   id = "hotel_reservation_booking_form",
 *   admin_label = @Translation("Hotel Booking Form"),
 *   category = @Translation("Hotel Reservation"),
 * )
 */
class BookingFormBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'show_conditions' => TRUE,
      'title_text' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form['title_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Form Title'),
      '#description' => $this->t('Leave empty to use the hotel name from settings.'),
      '#default_value' => $config['title_text'] ?? '',
    ];

    $form['show_conditions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show booking conditions'),
      '#description' => $this->t('Display the booking conditions text from module settings.'),
      '#default_value' => $config['show_conditions'] ?? TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['title_text'] = $form_state->getValue('title_text');
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
    $form_title = !empty($block_config['title_text']) ? $block_config['title_text'] : $hotel_name;

    $build = [];

    // Attach CSS/JS library.
    $build['#attached']['library'][] = 'hotel_reservation/booking-form';

    // Pass settings to JS (keys must match what booking-form.js expects).
    $build['#attached']['drupalSettings']['hotelReservation'] = [
      'currencySymbol' => $currency,
      'minStay' => $min_stay,
      'maxStay' => $max_stay,
      'checkInTime' => $check_in_time,
      'checkOutTime' => $check_out_time,
      'bookingConditions' => $booking_conditions,
      'apiCheckUrl' => Url::fromRoute('hotel_reservation.api_check_availability')->toString(),
      'apiSubmitUrl' => Url::fromRoute('hotel_reservation.api_submit_reservation')->toString(),
    ];

    // Add CSRF token for API submission.
    $build['#attached']['drupalSettings']['hotelReservation']['csrfToken'] = \Drupal::csrfToken()->get('/api/hotel-reservation/submit');

    // Build the complete booking form HTML matching CSS/JS expectations.
    $html = '<div class="hr-booking-form">';

    // Title.
    $html .= '<h2 class="hr-booking-form__title">' . $this->t('Book Your Stay') . '</h2>';
    $html .= '<p class="hr-booking-form__subtitle">' . $this->t('Check-in from @in, check-out until @out', [
      '@in' => $check_in_time,
      '@out' => $check_out_time,
    ]) . '</p>';

    // Step indicators.
    $html .= '<div class="hr-steps">';
    $html .= '<div class="hr-step active" data-step="search">';
    $html .= '<span class="hr-step-number">1</span>' . $this->t('Dates') . '</div>';
    $html .= '<div class="hr-step-connector"></div>';
    $html .= '<div class="hr-step" data-step="select">';
    $html .= '<span class="hr-step-number">2</span>' . $this->t('Room') . '</div>';
    $html .= '<div class="hr-step-connector"></div>';
    $html .= '<div class="hr-step" data-step="book">';
    $html .= '<span class="hr-step-number">3</span>' . $this->t('Details') . '</div>';
    $html .= '</div>';

    // === Section 1: Search ===
    $html .= '<div class="hr-section hr-section--search">';
    $html .= '<div class="hr-search-errors"></div>';

    $html .= '<div class="hr-form-row">';
    $html .= '<div class="hr-form-group">';
    $html .= '<label for="hr-check-in">' . $this->t('Check-in') . '</label>';
    $html .= '<input type="date" id="hr-check-in" class="hr-field-check-in" required>';
    $html .= '</div>';
    $html .= '<div class="hr-form-group">';
    $html .= '<label for="hr-check-out">' . $this->t('Check-out') . '</label>';
    $html .= '<input type="date" id="hr-check-out" class="hr-field-check-out" required>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div class="hr-form-group">';
    $html .= '<label>' . $this->t('Guests') . '</label>';
    $html .= '<div class="hr-guest-counter">';
    $html .= '<button type="button" class="hr-guest-counter__btn hr-guest-counter__btn--minus">−</button>';
    $html .= '<input type="number" class="hr-field-guests hr-guest-counter__value" value="1" min="1" max="20" readonly>';
    $html .= '<button type="button" class="hr-guest-counter__btn hr-guest-counter__btn--plus">+</button>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<button type="button" class="hr-btn hr-btn--primary hr-search-btn">' . $this->t('Search Available Rooms') . '</button>';
    $html .= '</div>';

    // === Section 2: Select Room ===
    $html .= '<div class="hr-section hr-section--select" style="display:none">';
    $html .= '<button type="button" class="hr-back-btn">← ' . $this->t('Change dates') . '</button>';
    $html .= '<div class="hr-results">';
    $html .= '<div class="hr-results__title"></div>';
    $html .= '<div class="hr-results-list"></div>';
    $html .= '</div>';
    $html .= '</div>';

    // === Section 3: Booking Details ===
    $html .= '<div class="hr-section hr-section--book" style="display:none">';
    $html .= '<button type="button" class="hr-back-btn">← ' . $this->t('Choose another room') . '</button>';
    $html .= '<div class="hr-book-errors"></div>';

    $html .= '<div class="hr-form-row">';
    $html .= '<div class="hr-form-group">';
    $html .= '<label>' . $this->t('Room') . '</label>';
    $html .= '<div class="hr-room-selected-name" style="padding:10px 14px;background:#f9fafb;border-radius:10px;border:1.5px solid #e5e7eb;font-weight:600;color:#1a1a2e;">—</div>';
    $html .= '</div>';
    $html .= '<div class="hr-form-group">';
    $html .= '<label>' . $this->t('Total') . '</label>';
    $html .= '<div class="hr-room-selected-price" style="padding:10px 14px;background:#fffbeb;border-radius:10px;border:1.5px solid #fde68a;font-weight:700;color:#d97706;font-size:18px;">0 ' . $currency . '</div>';
    $html .= '</div>';
    $html .= '</div>';

    // Price breakdown.
    $html .= '<div class="hr-price-breakdown" style="display:none">';
    $html .= '<div class="hr-price-breakdown__title">' . $this->t('Price Breakdown') . '</div>';
    $html .= '<div class="hr-price-breakdown-body"></div>';
    $html .= '</div>';

    $html .= '<input type="hidden" class="hr-field-room-id">';

    $html .= '<div class="hr-form-row">';
    $html .= '<div class="hr-form-group">';
    $html .= '<label for="hr-guest-name">' . $this->t('Full Name') . ' *</label>';
    $html .= '<input type="text" id="hr-guest-name" class="hr-field-guest-name" placeholder="' . $this->t('Your name') . '" required>';
    $html .= '</div>';
    $html .= '<div class="hr-form-group">';
    $html .= '<label for="hr-guest-phone">' . $this->t('Phone') . ' *</label>';
    $html .= '<input type="tel" id="hr-guest-phone" class="hr-field-guest-phone" placeholder="+7 (___) ___-__-__" required>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div class="hr-form-group">';
    $html .= '<label for="hr-guest-email">' . $this->t('Email') . '</label>';
    $html .= '<input type="email" id="hr-guest-email" class="hr-field-guest-email" placeholder="' . $this->t('your@email.com') . '">' ;
    $html .= '</div>';

    $html .= '<div class="hr-form-group">';
    $html .= '<label for="hr-notes">' . $this->t('Notes') . '</label>';
    $html .= '<textarea id="hr-notes" class="hr-field-notes" rows="2" placeholder="' . $this->t('Special requests...') . '"></textarea>';
    $html .= '</div>';

    // Booking conditions (if enabled).
    if (!empty($block_config['show_conditions']) && !empty($booking_conditions)) {
      $escaped_conditions = Html::escape($booking_conditions);
      $html .= '<div class="hr-terms">';
      $html .= '<div class="hr-terms__title">' . $this->t('Booking Conditions') . '</div>';
      $html .= '<div class="hr-terms__text">' . nl2br($escaped_conditions) . '</div>';
      $html .= '</div>';
    }

    $html .= '<button type="button" class="hr-btn hr-btn--primary hr-book-btn">' . $this->t('Book Now') . '</button>';
    $html .= '</div>';

    // === Section 4: Success ===
    $html .= '<div class="hr-section hr-section--success" style="display:none">';
    $html .= '<div class="hr-success">';
    $html .= '<div class="hr-success__icon">✓</div>';
    $html .= '<h3 class="hr-success__title">' . $this->t('Booking Submitted!') . '</h3>';
    $html .= '<p class="hr-success__text">' . $this->t('Your reservation #@id is pending confirmation. We will contact you shortly.', [
      '@id' => '<span class="hr-success-id"></span>',
    ]) . '</p>';
    $html .= '<button type="button" class="hr-btn hr-btn--secondary hr-new-search-btn" style="margin-top:16px;">' . $this->t('Make Another Booking') . '</button>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '</div>'; // .hr-booking-form

    $build['content'] = [
      '#type' => 'markup',
      '#markup' => $html,
      '#allowed_tags' => [
        'div', 'h2', 'h3', 'h4', 'p', 'label', 'input', 'button', 'textarea',
        'span', 'small', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'br', 'a', 'strong', 'em', 'pre',
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
