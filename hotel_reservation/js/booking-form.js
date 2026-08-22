/**
 * Hotel Reservation — Frontend Booking Form JS
 * Replaces .front-form-block with modern AJAX booking experience.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.hotelReservationBookingForm = {
    attach: function (context, settings) {
      const $form = $('.hr-booking-form', context);
      if ($form.length === 0) return;

      const config = drupalSettings.hotelReservation || {};
      const apiCheckUrl = config.apiCheckUrl || '/api/hotel-reservation/check-availability';
      const apiSubmitUrl = config.apiSubmitUrl || '/api/hotel-reservation/submit';
      const currencySymbol = config.currencySymbol || '₽';
      const minStay = parseInt(config.minStay) || 1;
      const maxStay = parseInt(config.maxStay) || 30;
      const checkInTime = config.checkInTime || '14:00';
      const checkOutTime = config.checkOutTime || '12:00';
      const bookingConditions = config.bookingConditions || '';

      // Set minimum dates
      const today = new Date().toISOString().split('T')[0];
      const $checkIn = $form.find('.hr-field-check-in');
      const $checkOut = $form.find('.hr-field-check-out');

      $checkIn.attr('min', today);
      $checkOut.attr('min', today);

      // State
      let currentStep = 'search';
      let selectedRoom = null;
      let searchResults = [];

      // ---- Step Navigation ----
      function showStep(step) {
        $form.find('.hr-step').removeClass('active');
        if (step === 'search') {
          $form.find('.hr-step[data-step="search"]').addClass('active');
          $form.find('.hr-step[data-step="search"]').addClass('completed');
        } else if (step === 'select') {
          $form.find('.hr-step[data-step="search"]').addClass('completed');
          $form.find('.hr-step[data-step="select"]').addClass('active');
        } else if (step === 'book') {
          $form.find('.hr-step[data-step="search"]').addClass('completed');
          $form.find('.hr-step[data-step="select"]').addClass('completed');
          $form.find('.hr-step[data-step="book"]').addClass('active');
        } else if (step === 'success') {
          $form.find('.hr-step[data-step="search"]').addClass('completed');
          $form.find('.hr-step[data-step="select"]').addClass('completed');
          $form.find('.hr-step[data-step="book"]').addClass('completed');
        }

        $form.find('.hr-section').hide();
        $form.find('.hr-section--' + step).show();
        currentStep = step;
      }

      // ---- Validate Dates ----
      function validateDates() {
        const checkIn = $checkIn.val();
        const checkOut = $checkOut.val();
        const errors = [];

        if (!checkIn) {
          errors.push(Drupal.t('Select check-in date'));
        }
        if (!checkOut) {
          errors.push(Drupal.t('Select check-out date'));
        }
        if (checkIn && checkOut) {
          const d1 = new Date(checkIn);
          const d2 = new Date(checkOut);
          const nights = Math.ceil((d2 - d1) / (1000 * 60 * 60 * 24));
          if (nights <= 0) {
            errors.push(Drupal.t('Check-out must be after check-in'));
          } else if (nights < minStay) {
            errors.push(Drupal.t('Minimum stay is @n nights', {'@n': minStay}));
          } else if (nights > maxStay) {
            errors.push(Drupal.t('Maximum stay is @n nights', {'@n': maxStay}));
          }
        }

        return errors;
      }

      // ---- Show Errors ----
      function showErrors(container, errors) {
        const $container = $form.find(container);
        $container.empty();
        if (errors.length === 0) return;
        const html = errors.map(e => '<div class="hr-error">' + Drupal.checkPlain(e) + '</div>').join('');
        $container.html(html);
      }

      // ---- Search Available Rooms ----
      function searchRooms() {
        const errors = validateDates();
        showErrors('.hr-search-errors', errors);
        if (errors.length > 0) return;

        const $btn = $form.find('.hr-search-btn');
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="hr-spinner"></span>' + Drupal.t('Searching...'));

        const guestCount = parseInt($form.find('.hr-field-guests').val()) || 1;

        $.ajax({
          url: apiCheckUrl,
          method: 'POST',
          contentType: 'application/json',
          dataType: 'json',
          data: JSON.stringify({
            check_in: $checkIn.val(),
            check_out: $checkOut.val(),
            guest_count: guestCount
          }),
          headers: {
            'X-CSRF-Token': Drupal.csrfToken || $('meta[name="csrf-token"]').attr('content')
          },
          success: function (response) {
            searchResults = response.rooms || [];
            renderResults(searchResults);
            showStep('select');
          },
          error: function (xhr) {
            let msg = Drupal.t('An error occurred. Please try again.');
            try {
              const data = JSON.parse(xhr.responseText);
              if (data.message) msg = data.message;
            } catch (e) {}
            showErrors('.hr-search-errors', [msg]);
          },
          complete: function () {
            $btn.prop('disabled', false).html(originalText);
          }
        });
      }

      // ---- Render Search Results ----
      function renderResults(rooms) {
        const $container = $form.find('.hr-results-list');
        $container.empty();

        if (rooms.length === 0) {
          $container.html(
            '<div class="hr-no-results">' +
            '<div class="hr-no-results__icon">🏨</div>' +
            '<p class="hr-no-results__text">' + Drupal.t('No rooms available for selected dates') + '</p>' +
            '</div>'
          );
          return;
        }

        const checkIn = $checkIn.val();
        const checkOut = $checkOut.val();
        const d1 = new Date(checkIn);
        const d2 = new Date(checkOut);
        const nights = Math.ceil((d2 - d1) / (1000 * 60 * 60 * 24));

        let html = '<div class="hr-results__title">' +
          Drupal.t('Found @n room(s)', {'@n': rooms.length}) +
          ' · ' + nights + ' ' + Drupal.formatPlural(nights, 'night', 'nights') +
          '</div>';

        rooms.forEach(function (room) {
          const amenities = (room.amenities || '').split(',').map(a => a.trim()).filter(a => a);
          const amenitiesHtml = amenities.map(a => '<span class="hr-room-card__amenity">' + Drupal.checkPlain(a) + '</span>').join('');

          html +=
            '<div class="hr-room-card" data-room-id="' + room.id + '">' +
            '<div class="hr-room-card__name">' + Drupal.checkPlain(room.name) + '</div>' +
            '<div class="hr-room-card__meta">' +
            Drupal.t('Up to @n guests', {'@n': room.capacity}) +
            '</div>' +
            (amenitiesHtml ? '<div class="hr-room-card__amenities">' + amenitiesHtml + '</div>' : '') +
            '<div class="hr-room-card__price">' +
            '<div><span class="hr-room-card__price-value">' + currencySymbol + parseFloat(room.total_price).toLocaleString('ru-RU') + '</span>' +
            '<span class="hr-room-card__price-unit"> / ' + Drupal.formatPlural(nights, 'night', 'nights') + '</span></div>' +
            (room.base_price !== room.total_price / nights ?
              '<div class="hr-room-card__total">' + Drupal.t('from') + ' ' + currencySymbol + parseFloat(room.base_price).toLocaleString('ru-RU') + '/' + Drupal.t('night') + '</div>' : '') +
            '</div></div>';
        });

        $container.html(html);
      }

      // ---- Select Room ----
      function selectRoom(roomId) {
        selectedRoom = searchResults.find(function (r) { return r.id === roomId; });
        if (!selectedRoom) return;

        $form.find('.hr-room-card').removeClass('selected');
        $form.find('.hr-room-card[data-room-id="' + roomId + '"]').addClass('selected');

        // Populate booking form
        $form.find('.hr-field-room-id').val(roomId);
        $form.find('.hr-room-selected-name').text(selectedRoom.name);
        $form.find('.hr-room-selected-price').text(currencySymbol + parseFloat(selectedRoom.total_price).toLocaleString('ru-RU'));

        // Price breakdown
        renderPriceBreakdown(selectedRoom);
        showStep('book');
      }

      // ---- Price Breakdown ----
      function renderPriceBreakdown(room) {
        const $container = $form.find('.hr-price-breakdown-body');
        $container.empty();

        if (!room.daily_prices) {
          $container.closest('.hr-price-breakdown').hide();
          return;
        }

        const days = Object.keys(room.daily_prices).sort();
        const basePrice = parseFloat(room.base_price);
        let hasCustom = false;

        let html = '<table><thead><tr><th>' + Drupal.t('Date') + '</th><th>' + Drupal.t('Price') + '</th></tr></thead><tbody>';

        days.forEach(function (date) {
          const price = parseFloat(room.daily_prices[date]);
          if (price !== basePrice) hasCustom = true;
          html += '<tr><td>' + formatDateRu(date) + '</td><td>' + currencySymbol + price.toLocaleString('ru-RU') +
            (price !== basePrice ? ' <small style="color:#d97706">★</small>' : '') + '</td></tr>';
        });

        html += '</tbody>';
        html += '<tfoot><tr class="hr-price-breakdown__total"><td>' + Drupal.t('Total') + '</td>' +
          '<td>' + currencySymbol + parseFloat(room.total_price).toLocaleString('ru-RU') + '</td></tr></tfoot>';
        html += '</table>';

        $container.html(html);
        if (!hasCustom) {
          $container.closest('.hr-price-breakdown').hide();
        } else {
          $container.closest('.hr-price-breakdown').show();
        }
      }

      // ---- Submit Reservation ----
      function submitReservation() {
        const errors = [];
        const guestName = $form.find('.hr-field-guest-name').val().trim();
        const guestPhone = $form.find('.hr-field-guest-phone').val().trim();
        const guestEmail = $form.find('.hr-field-guest-email').val().trim();
        const guestCount = parseInt($form.find('.hr-field-guests').val()) || 1;
        const notes = $form.find('.hr-field-notes').val().trim();

        if (!guestName) errors.push(Drupal.t('Enter your name'));
        if (!guestPhone) errors.push(Drupal.t('Enter your phone number'));
        if (guestEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(guestEmail)) {
          errors.push(Drupal.t('Enter a valid email address'));
        }

        showErrors('.hr-book-errors', errors);
        if (errors.length > 0) return;

        const $btn = $form.find('.hr-book-btn');
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="hr-spinner"></span>' + Drupal.t('Booking...'));

        $.ajax({
          url: apiSubmitUrl,
          method: 'POST',
          contentType: 'application/json',
          dataType: 'json',
          data: JSON.stringify({
            room_id: selectedRoom.id,
            check_in: $checkIn.val(),
            check_out: $checkOut.val(),
            guest_name: guestName,
            guest_phone: guestPhone,
            guest_email: guestEmail,
            guest_count: guestCount,
            notes: notes
          }),
          headers: {
            'X-CSRF-Token': Drupal.csrfToken || $('meta[name="csrf-token"]').attr('content')
          },
          success: function (response) {
            showStep('success');
            $form.find('.hr-success-id').text(response.reservation_id || '');
          },
          error: function (xhr) {
            let msg = Drupal.t('Booking failed. Please try again.');
            try {
              const data = JSON.parse(xhr.responseText);
              if (data.message) msg = data.message;
              if (data.errors && data.errors.length) msg = data.errors.join(' ');
            } catch (e) {}
            showErrors('.hr-book-errors', [msg]);
          },
          complete: function () {
            $btn.prop('disabled', false).html(originalText);
          }
        });
      }

      // ---- Format Date (Russian) ----
      function formatDateRu(dateStr) {
        const months = {
          '01': 'янв', '02': 'фев', '03': 'мар', '04': 'апр',
          '05': 'май', '06': 'июн', '07': 'июл', '08': 'авг',
          '09': 'сен', '10': 'окт', '11': 'ноя', '12': 'дек'
        };
        const parts = dateStr.split('-');
        return parseInt(parts[2]) + ' ' + months[parts[1]];
      }

      // ---- Event Listeners ----
      // Search button
      $form.on('click', '.hr-search-btn', function (e) {
        e.preventDefault();
        searchRooms();
      });

      // Room card click
      $form.on('click', '.hr-room-card', function () {
        const roomId = parseInt($(this).data('room-id'));
        selectRoom(roomId);
      });

      // Back buttons
      $form.on('click', '.hr-back-btn', function (e) {
        e.preventDefault();
        if (currentStep === 'book') {
          showStep('select');
        } else if (currentStep === 'select') {
          showStep('search');
        }
      });

      // Book button
      $form.on('click', '.hr-book-btn', function (e) {
        e.preventDefault();
        submitReservation();
      });

      // New search button (from success)
      $form.on('click', '.hr-new-search-btn', function (e) {
        e.preventDefault();
        selectedRoom = null;
        searchResults = [];
        $form.find('.hr-room-card').removeClass('selected');
        showStep('search');
      });

      // Guest counter
      $form.on('click', '.hr-guest-counter__btn--minus', function () {
        const $input = $(this).siblings('.hr-guest-counter__value');
        let val = parseInt($input.val()) || 1;
        if (val > 1) $input.val(val - 1);
      });

      $form.on('click', '.hr-guest-counter__btn--plus', function () {
        const $input = $(this).siblings('.hr-guest-counter__value');
        let val = parseInt($input.val()) || 1;
        if (val < 20) $input.val(val + 1);
      });

      // Auto-set check-out minimum when check-in changes
      $form.on('change', '.hr-field-check-in', function () {
        const val = $(this).val();
        if (val) {
          const next = new Date(val);
          next.setDate(next.getDate() + minStay);
          $checkOut.attr('min', next.toISOString().split('T')[0]);
          if ($checkOut.val() && new Date($checkOut.val()) <= new Date(val)) {
            $checkOut.val(next.toISOString().split('T')[0]);
          }
        }
      });

      // Init
      showStep('search');
    }
  };

})(jQuery, Drupal, drupalSettings);
