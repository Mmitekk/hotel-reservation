/**
 * Hotel Reservation — Frontend Booking Form JS
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  // ---- Color Utility Functions ----
  function hexToRgb(hex) {
    if (!hex || hex.charAt(0) !== '#') return { r: 0, g: 0, b: 0 };
    hex = hex.replace('#', '');
    if (hex.length === 3) hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    const num = parseInt(hex, 16);
    return { r: (num >> 16) & 255, g: (num >> 8) & 255, b: num & 255 };
  }

  function hexToRgba(hex, alpha) {
    const c = hexToRgb(hex);
    return 'rgba(' + c.r + ',' + c.g + ',' + c.b + ',' + alpha + ')';
  }

  function darkenColor(hex, amount) {
    const c = hexToRgb(hex);
    const r = Math.max(0, c.r - amount);
    const g = Math.max(0, c.g - amount);
    const b = Math.max(0, c.b - amount);
    return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
  }

  function lightenColor(hex, amount) {
    const c = hexToRgb(hex);
    const r = Math.min(255, c.r + amount);
    const g = Math.min(255, c.g + amount);
    const b = Math.min(255, c.b + amount);
    return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
  }

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

      // ---- Apply color settings from config ----
      const primaryColor = config.formPrimaryColor || '#d97706';
      const bgColor = config.formBackgroundColor || '#ffffff';
      const textColor = config.formTextColor || '#1a1a2e';
      const borderRadius = config.formBorderRadius || 10;

      $form.css({
        '--hr-primary': primaryColor,
        '--hr-primary-dark': darkenColor(primaryColor, 30),
        '--hr-primary-light': lightenColor(primaryColor, 50),
        '--hr-primary-bg': hexToRgba(primaryColor, 0.08),
        '--hr-primary-border': hexToRgba(primaryColor, 0.25),
        '--hr-text': textColor,
        '--hr-bg': bgColor,
        '--hr-btn-text': '#ffffff',
      });

      // Set min datetime-local to now.
      const now = new Date();
      const pad = (n) => String(n).padStart(2, '0');
      const nowLocal = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate())
        + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());

      const $checkIn = $form.find('.hr-field-check-in');
      const $checkOut = $form.find('.hr-field-check-out');

      $checkIn.attr('min', nowLocal);
      $checkOut.attr('min', nowLocal);

      // Set default times from config.
      if (!$checkIn.val()) {
        const todayStr = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
        $checkIn.val(todayStr + 'T' + checkInTime);
      }

      // State.
      let currentStep = 'search';
      let selectedRoom = null;
      let searchResults = [];

      // ---- Extract date (Y-m-d) from datetime-local value ----
      function extractDate(dtLocalVal) {
        if (!dtLocalVal) return '';
        return dtLocalVal.split('T')[0];
      }

      // ---- Step Navigation ----
      function showStep(step) {
        $form.find('.hr-step').removeClass('active');
        if (step === 'search') {
          $form.find('.hr-step[data-step="search"]').addClass('active completed');
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
        const checkIn = extractDate($checkIn.val());
        const checkOut = extractDate($checkOut.val());
        const errors = [];

        if (!checkIn) errors.push(Drupal.t('Выберите дату заезда'));
        if (!checkOut) errors.push(Drupal.t('Выберите дату выезда'));
        if (checkIn && checkOut) {
          const d1 = new Date(checkIn);
          const d2 = new Date(checkOut);
          const nights = Math.ceil((d2 - d1) / (1000 * 60 * 60 * 24));
          if (nights <= 0) errors.push(Drupal.t('Дата выезда должна быть позже даты заезда'));
          else if (nights < minStay) errors.push(Drupal.t('Минимальное количество ночей: @n', {'@n': minStay}));
          else if (nights > maxStay) errors.push(Drupal.t('Максимальное количество ночей: @n', {'@n': maxStay}));
        }
        return errors;
      }

      // ---- Show Errors ----
      function showErrors(container, errors) {
        const $container = $form.find(container);
        $container.empty();
        if (errors.length === 0) return;
        $container.html(errors.map(e => '<div class="hr-error">' + Drupal.checkPlain(e) + '</div>').join(''));
      }

      // ---- Search Available Rooms ----
      function searchRooms() {
        const errors = validateDates();
        showErrors('.hr-search-errors', errors);
        if (errors.length > 0) return;

        const $btn = $form.find('.hr-search-btn');
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="hr-spinner"></span>' + Drupal.t('Поиск...'));

        const guestCount = parseInt($form.find('.hr-field-guests').val()) || 1;

        $.ajax({
          url: apiCheckUrl,
          method: 'POST',
          contentType: 'application/json',
          dataType: 'json',
          data: JSON.stringify({
            check_in: extractDate($checkIn.val()),
            check_out: extractDate($checkOut.val()),
            guest_count: guestCount
          }),
          success: function (response) {
            searchResults = response.rooms || [];
            renderResults(searchResults);
            showStep('select');
          },
          error: function (xhr) {
            let msg = Drupal.t('Произошла ошибка. Попробуйте ещё раз.');
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
            '<p class="hr-no-results__text">' + Drupal.t('Нет свободных номеров на выбранные даты') + '</p>' +
            '</div>'
          );
          return;
        }

        const checkIn = extractDate($checkIn.val());
        const checkOut = extractDate($checkOut.val());
        const d1 = new Date(checkIn);
        const d2 = new Date(checkOut);
        const nights = Math.ceil((d2 - d1) / (1000 * 60 * 60 * 24));

        let html = '<div class="hr-results__title">' +
          Drupal.t('Найдено @n номер(ов)', {'@n': rooms.length}) +
          ' · ' + nights + ' ' + Drupal.formatPlural(nights, 'ночь', 'ночи', 'ночей') +
          '</div>';

        rooms.forEach(function (room) {
          const amenities = (room.amenities || '').split(',').map(a => a.trim()).filter(a => a);
          const amenitiesHtml = amenities.map(a => '<span class="hr-room-card__amenity">' + Drupal.checkPlain(a) + '</span>').join('');

          html +=
            '<div class="hr-room-card" data-room-id="' + room.id + '">' +
            '<div class="hr-room-card__name">' + Drupal.checkPlain(room.name) + '</div>' +
            '<div class="hr-room-card__meta">' +
            Drupal.t('До @n гостей', {'@n': room.capacity}) +
            '</div>' +
            (amenitiesHtml ? '<div class="hr-room-card__amenities">' + amenitiesHtml + '</div>' : '') +
            '<div class="hr-room-card__price">' +
            '<div><span class="hr-room-card__price-value">' + currencySymbol + parseFloat(room.total_price).toLocaleString('ru-RU') + '</span>' +
            '<span class="hr-room-card__price-unit"> / ' + Drupal.formatPlural(nights, 'ночь', 'ночи', 'ночей') + '</span></div>' +
            (room.base_price !== room.total_price / nights ?
              '<div class="hr-room-card__total">' + Drupal.t('от') + ' ' + currencySymbol + parseFloat(room.base_price).toLocaleString('ru-RU') + '/' + Drupal.t('ночь') + '</div>' : '') +
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

        $form.find('.hr-field-room-id').val(roomId);
        $form.find('.hr-room-selected-name').text(selectedRoom.name);
        $form.find('.hr-room-selected-price').text(currencySymbol + parseFloat(selectedRoom.total_price).toLocaleString('ru-RU'));

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

        let html = '<table><thead><tr><th>' + Drupal.t('Дата') + '</th><th>' + Drupal.t('Цена') + '</th></tr></thead><tbody>';

        days.forEach(function (date) {
          const price = parseFloat(room.daily_prices[date]);
          if (price !== basePrice) hasCustom = true;
          html += '<tr><td>' + formatDateRu(date) + '</td><td>' + currencySymbol + price.toLocaleString('ru-RU') +
            (price !== basePrice ? ' <small style="color:var(--hr-primary)">★</small>' : '') + '</td></tr>';
        });

        html += '</tbody>';
        html += '<tfoot><tr class="hr-price-breakdown__total"><td>' + Drupal.t('Итого') + '</td>' +
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

        if (!guestName) errors.push(Drupal.t('Введите ваше имя'));
        if (!guestPhone || guestPhone.replace(/[^\d]/g, '').length < 11) errors.push(Drupal.t('Введите корректный номер телефона'));
        if (guestEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(guestEmail)) {
          errors.push(Drupal.t('Введите корректный email'));
        }

        showErrors('.hr-book-errors', errors);
        if (errors.length > 0) return;

        const $btn = $form.find('.hr-book-btn');
        const originalText = $btn.html();
        const loadingText = Drupal.t('Бронирование...');
        $btn.prop('disabled', true).html('<span class="hr-spinner"></span>' + loadingText);

        $.ajax({
          url: apiSubmitUrl,
          method: 'POST',
          contentType: 'application/json',
          dataType: 'json',
          data: JSON.stringify({
            room_id: selectedRoom.id,
            check_in: extractDate($checkIn.val()),
            check_out: extractDate($checkOut.val()),
            guest_name: guestName,
            guest_phone: guestPhone,
            guest_email: guestEmail,
            guest_count: guestCount,
            notes: notes
          }),
          success: function (response) {
            showStep('success');
            $form.find('.hr-success-id').text(response.reservation_id || '');
            // Replace @id in success text.
            const template = $form.find('.hr-success__text').data('template') || '';
            if (template && response.reservation_id) {
              $form.find('.hr-success__text').html(
                Drupal.checkPlain(template.replace('@id', response.reservation_id))
              );
            }
          },
          error: function (xhr) {
            let msg = Drupal.t('Ошибка бронирования. Попробуйте ещё раз.');
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
        selectRoom(parseInt($(this).data('room-id')));
      });

      // Back buttons
      $form.on('click', '.hr-back-btn', function (e) {
        e.preventDefault();
        if (currentStep === 'book') showStep('select');
        else if (currentStep === 'select') showStep('search');
      });

      // Book button
      $form.on('click', '.hr-book-btn', function (e) {
        e.preventDefault();
        submitReservation();
      });

      // New search button
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

      // Auto-set check-out min when check-in changes
      $form.on('change', '.hr-field-check-in', function () {
        const val = $(this).val();
        if (val) {
          const next = new Date(val);
          next.setDate(next.getDate() + minStay);
          const nextStr = next.getFullYear() + '-' + pad(next.getMonth() + 1) + '-' + pad(next.getDate())
            + 'T' + checkOutTime;
          $checkOut.attr('min', val);
          if ($checkOut.val() && new Date($checkOut.val()) <= new Date(val)) {
            $checkOut.val(nextStr);
          }
        }
      });

      // ---- Phone Mask +7 ----
      $form.on('input', '.hr-field-guest-phone', function () {
        let val = $(this).val().replace(/[^0-9+]/g, '');
        if (val.length > 0 && val[0] !== '+') {
          if (val[0] === '8') val = '7' + val.substring(1);
          if (val[0] === '7') val = '+' + val;
          else val = '+7' + val;
        }
        if (val.startsWith('+7')) {
          const digits = val.substring(2);
          let formatted = '+7';
          if (digits.length > 0) formatted += ' (' + digits.substring(0, 3);
          if (digits.length >= 3) formatted += ') ' + digits.substring(3, 6);
          if (digits.length >= 6) formatted += '-' + digits.substring(6, 8);
          if (digits.length >= 8) formatted += '-' + digits.substring(8, 10);
          $(this).val(formatted);
        } else {
          $(this).val(val);
        }
      });

      // Focus formats phone field
      $form.on('focus', '.hr-field-guest-phone', function () {
        if (!this.value || this.value.replace(/[^\d]/g, '').length === 0) {
          this.value = '+7 (';
        }
      });

      // Blur cleans up incomplete phone
      $form.on('blur', '.hr-field-guest-phone', function () {
        const digits = this.value.replace(/[^\d]/g, '');
        if (digits.length < 11) {
          this.value = '';
        }
      });

      // Init
      showStep('search');
    }
  };

})(jQuery, Drupal, drupalSettings);
