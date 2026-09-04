/**
 * @file
 * Shared room detail modal with slick-like carousel, lightbox and booking.
 *
 * Exposes Drupal.hotelReservationRoomModal.open(room, options).
 * Room shape: {id, name, type_label, type_color, capacity, price,
 *   amenities[], teaser, description, slides[{url, alt}]}.
 */

(function ($, Drupal) {
  'use strict';

  var NAMESPACE = '.hrRoomModal';
  var GAP = 12;

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
    $('.hr-room-lightbox-overlay').remove();
    $('.hr-room-modal-overlay').remove();
    $('body').css('overflow', '');
    $(document).off('keydown' + NAMESPACE);
    $(window).off('resize' + NAMESPACE);
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

  function openLightbox($overlay, slides, index) {
    $('.hr-room-lightbox-overlay').remove();
    var idx = index;
    var total = slides.length;

    function render() {
      var s = slides[idx];
      var html = '<div class="hr-room-lightbox-overlay" role="dialog" aria-modal="true" aria-label="' + esc(s.alt || '') + '">';
      html += '<button type="button" class="hr-room-lightbox__close" aria-label="Закрыть просмотр">' + ICON_CLOSE + '</button>';
      html += '<img src="' + esc(s.url) + '" alt="' + esc(s.alt || '') + '">';
      if (total > 1) {
        html += '<button type="button" class="hr-room-modal__nav hr-room-modal__nav--prev" aria-label="Предыдущее фото">' + CHEVRON_LEFT + '</button>';
        html += '<button type="button" class="hr-room-modal__nav hr-room-modal__nav--next" aria-label="Следующее фото">' + CHEVRON_RIGHT + '</button>';
        html += '<div class="hr-room-lightbox__counter">' + (idx + 1) + ' / ' + total + '</div>';
      }
      html += '</div>';
      $('.hr-room-lightbox-overlay').remove();
      var $lb = $(html);
      $('body').append($lb);
      $lb.on('click', function (e) {
        if (e.target === this) {
          closeLightbox();
        }
      });
      $lb.find('.hr-room-lightbox__close').on('click', function (e) {
        e.stopPropagation();
        closeLightbox();
      });
      $lb.find('.hr-room-modal__nav--prev').on('click', function (e) {
        e.stopPropagation();
        step(-1);
      });
      $lb.find('.hr-room-modal__nav--next').on('click', function (e) {
        e.stopPropagation();
        step(1);
      });
    }

    function step(d) {
      idx = ((idx + d) % total + total) % total;
      render();
    }

    function closeLightbox() {
      $('.hr-room-lightbox-overlay').remove();
      $overlay.data('hrLightboxStep', null);
    }

    $overlay.data('hrLightboxStep', step);
    render();
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
        html += '<div class="hr-room-modal__slide" title="Нажмите, чтобы увеличить">';
        html += '<img src="' + esc(s.url) + '" alt="' + esc(s.alt || room.name) + '" loading="lazy" draggable="false"></div>';
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
      if ($('.hr-room-lightbox-overlay').length) {
        var step = $overlay.data('hrLightboxStep');
        if (e.key === 'Escape') { $('.hr-room-lightbox-overlay').remove(); }
        else if ((e.key === 'ArrowLeft' || e.key === 'ArrowRight') && step) {
          step(e.key === 'ArrowRight' ? 1 : -1);
        }
        return;
      }
      if (e.key === 'Escape') { close(); }
      else if (e.key === 'ArrowLeft') { show(idx - 1); }
      else if (e.key === 'ArrowRight') { show(idx + 1); }
    });

    // Slick-like center mode: active slide centered, neighbours peek.
    var idx = 0;
    var $track = $overlay.find('.hr-room-modal__track');
    var $vp = $overlay.find('.hr-room-modal__track-viewport');
    var $slides = $overlay.find('.hr-room-modal__slide');

    function layout() {
      if (!slides.length) { return; }
      var vpW = $vp.width();
      var slideW = $slides.eq(0).outerWidth();
      var x = (vpW - slideW) / 2 - idx * (slideW + GAP);
      $track.css('transform', 'translateX(' + x + 'px)');
    }

    function show(i) {
      if (!slides.length) { return; }
      idx = ((i % slides.length) + slides.length) % slides.length;
      $slides.removeClass('is-active').eq(idx).addClass('is-active');
      $overlay.find('.hr-room-modal__dot').removeClass('is-active').eq(idx).addClass('is-active');
      layout();
    }

    $overlay.on('click', '.hr-room-modal__nav--prev', function () { show(idx - 1); });
    $overlay.on('click', '.hr-room-modal__nav--next', function () { show(idx + 1); });
    $overlay.on('click', '.hr-room-modal__dot', function () { show(parseInt($(this).attr('data-index'), 10)); });
    $overlay.on('click', '.hr-room-modal__slide', function () {
      openLightbox($overlay, slides, $slides.index(this));
    });
    $overlay.on('click', '.hr-room-modal__book', function () { bookRoom(room); });
    $(window).on('resize' + NAMESPACE, layout);

    show(0);
  }

  Drupal.hotelReservationRoomModal = Drupal.hotelReservationRoomModal || {
    open: open,
    close: close
  };

})(jQuery, Drupal);
