/**
 * @file
 * Shared room detail modal with carousel and booking button.
 *
 * Exposes Drupal.hotelReservationRoomModal.open(room, options).
 * Room shape: {id, name, type_label, type_color, capacity, price,
 *   amenities[], teaser, description, slides[{url, alt}]}.
 */

(function ($, Drupal) {
  'use strict';

  var NAMESPACE = '.hrRoomModal';

  var CHEVRON_LEFT = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  var CHEVRON_RIGHT = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  var ICON_CLOSE = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>';

  function esc(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str === undefined || str === null ? '' : str)));
    return div.innerHTML;
  }

  function guestWord(n) {
    n = parseInt(n, 10) || 0;
    var abs = Math.abs(n) % 100;
    var d = abs % 10;
    if (abs > 10 && abs < 20) { return 'гостей'; }
    if (d > 1 && d < 5) { return 'гостя'; }
    if (d === 1) { return 'гость'; }
    return 'гостей';
  }

  function close() {
    $('.hr-room-modal-overlay').remove();
    $('body').css('overflow', '');
    $(document).off('keydown' + NAMESPACE);
  }

  function bookRoom(room) {
    var roomId = room && room.id;
    close();
    try {
      document.dispatchEvent(new CustomEvent('hr:book-room', {detail: {roomId: roomId}}));
    }
    catch (e) { /* Older browsers — ignore. */ }
    var $previewBtn = $('.hr-booking-preview__btn').first();
    if ($previewBtn.length) {
      $previewBtn.trigger('click');
      return;
    }
    var $form = $('.hr-booking-form').first();
    if ($form.length && $form[0].scrollIntoView) {
      $form[0].scrollIntoView({behavior: 'smooth', block: 'start'});
    }
  }

  function open(room, options) {
    if (!room) {
      return;
    }
    options = options || {};
    var width = parseInt(options.width, 10) || 65;
    close();

    var slides = room.slides || [];
    var html = '<div class="hr-room-modal-overlay" role="dialog" aria-modal="true" aria-label="' + esc(room.name) + '">';
    html += '<div class="hr-room-modal" style="--hr-room-modal-width:' + width + '%">';
    html += '<button type="button" class="hr-room-modal__close" aria-label="Закрыть">' + ICON_CLOSE + '</button>';

    if (slides.length) {
      html += '<div class="hr-room-modal__slider"><div class="hr-room-modal__track-viewport"><div class="hr-room-modal__track">';
      slides.forEach(function (s) {
        html += '<div class="hr-room-modal__slide">';
        html += '<img src="' + esc(s.url) + '" alt="' + esc(s.alt || room.name) + '" loading="lazy"></div>';
      });
      html += '</div></div>';
      if (slides.length > 1) {
        html += '<button type="button" class="hr-room-modal__nav hr-room-modal__nav--prev" aria-label="Предыдущее фото">' + CHEVRON_LEFT + '</button>';
        html += '<button type="button" class="hr-room-modal__nav hr-room-modal__nav--next" aria-label="Следующее фото">' + CHEVRON_RIGHT + '</button>';
        html += '<div class="hr-room-modal__dots">';
        slides.forEach(function (s, i) {
          html += '<button type="button" class="hr-room-modal__dot' + (i === 0 ? ' is-active' : '') + '" data-index="' + i + '" aria-label="Фото ' + (i + 1) + '"></button>';
        });
        html += '</div>';
      }
      html += '</div>';
    }

    html += '<div class="hr-room-modal__body">';
    html += '<span class="hr-room-card__type-badge" style="background:' + esc(room.type_color || '#6b7280') + ';color:#fff">' + esc(room.type_label || '') + '</span>';
    html += '<h3 class="hr-room-modal__name">' + esc(room.name) + '</h3>';
    html += '<div class="hr-room-modal__meta"><span>👥 До ' + (parseInt(room.capacity, 10) || 0) + ' ' + esc(guestWord(room.capacity)) + '</span>';
    html += '<span class="hr-room-modal__price">' + esc(room.price || '') + ' /ночь</span></div>';
    if (room.amenities && room.amenities.length) {
      html += '<div class="hr-room-card__amenities">';
      room.amenities.forEach(function (a) {
        html += '<span class="hr-room-card__amenity">' + esc(a) + '</span>';
      });
      html += '</div>';
    }
    if (room.description) {
      html += '<p class="hr-room-modal__desc">' + esc(room.description) + '</p>';
    }
    else if (room.teaser) {
      html += '<p class="hr-room-modal__desc">' + esc(room.teaser) + '</p>';
    }
    html += '<button type="button" class="hr-room-modal__book">Забронировать</button>';
    html += '</div></div></div>';

    var $overlay = $(html);
    $('body').append($overlay).css('overflow', 'hidden');
    $overlay.find('.hr-room-modal__close').on('click', close);
    $overlay.on('click', function (e) {
      if (e.target === this) { close(); }
    });
    $(document).on('keydown' + NAMESPACE, function (e) {
      if (e.key === 'Escape') { close(); }
    });

    var idx = 0;
    function show(i) {
      idx = ((i % slides.length) + slides.length) % slides.length;
      $overlay.find('.hr-room-modal__track').css('transform', 'translateX(-' + (idx * 100) + '%)');
      $overlay.find('.hr-room-modal__dot').removeClass('is-active').eq(idx).addClass('is-active');
    }
    $overlay.on('click', '.hr-room-modal__nav--prev', function () { show(idx - 1); });
    $overlay.on('click', '.hr-room-modal__nav--next', function () { show(idx + 1); });
    $overlay.on('click', '.hr-room-modal__dot', function () { show(parseInt($(this).attr('data-index'), 10)); });
    $overlay.on('click', '.hr-room-modal__book', function () { bookRoom(room); });
  }

  Drupal.hotelReservationRoomModal = Drupal.hotelReservationRoomModal || {
    open: open,
    close: close
  };

})(jQuery, Drupal);
