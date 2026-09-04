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

  var CHEVRON_LEFT = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  var CHEVRON_RIGHT = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  var ICON_CLOSE = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>';
  var ICON_ZOOM = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2.5"/><path d="M21 21l-4.3-4.3M11 8v6M8 11h6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>';

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
        html += '<div class="hr-room-modal__slide">';
        html += '<img src="' + esc(s.url) + '" alt="' + esc(s.alt || room.name) + '" loading="lazy" draggable="false">';
        html += '<button type="button" class="hr-room-modal__zoom" aria-label="Открыть фото на весь экран">' + ICON_ZOOM + '</button></div>';
      });
      html += '</div></div>';
      if (slides.length > 1) {
        html += '<button type="button" class="hr-room-modal__nav hr-room-modal__nav--prev" aria-label="Предыдущее фото">' + CHEVRON_LEFT + '</button>';
        html += '<button type="button" class="hr-room-modal__nav hr-room-modal__nav--next" aria-label="Следующее фото">' + CHEVRON_RIGHT + '</button>';
        html += '<div class="hr-room-modal__counter">1 / ' + slides.length + '</div>';
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
      else if (e.key === 'ArrowLeft') { go(idx - 1); }
      else if (e.key === 'ArrowRight') { go(idx + 1); }
    });

    // Free-scroll carousel: row of slides, drag with mouse, arrows snap.
    var idx = 0;
    var $vp = $overlay.find('.hr-room-modal__track-viewport');
    var $slides = $overlay.find('.hr-room-modal__slide');
    var $counter = $overlay.find('.hr-room-modal__counter');
    var justDragged = false;

    function activeIndex() {
      if (!slides.length) { return 0; }
      var vpR = $vp[0].getBoundingClientRect();
      var mid = vpR.left + vpR.width / 2;
      var best = 0;
      var bestD = Infinity;
      $slides.each(function (i) {
        var r = this.getBoundingClientRect();
        var d = Math.abs((r.left + r.width / 2) - mid);
        if (d < bestD) { bestD = d; best = i; }
      });
      return best;
    }

    function updateCounter() {
      idx = activeIndex();
      $counter.text((idx + 1) + ' / ' + slides.length);
    }

    var scrollRaf = null;
    $vp.on('scroll' + NAMESPACE, function () {
      if (scrollRaf) { return; }
      scrollRaf = requestAnimationFrame(function () {
        scrollRaf = null;
        updateCounter();
      });
    });

    function go(i, instant) {
      if (!slides.length) { return; }
      idx = ((i % slides.length) + slides.length) % slides.length;
      try {
        $slides[idx].scrollIntoView({behavior: instant ? 'auto' : 'smooth', inline: 'center', block: 'nearest'});
      }
      catch (e) {
        $slides[idx].scrollIntoView(instant ? true : false);
      }
      updateCounter();
    }

    // Drag-to-scroll with mouse.
    var dragging = false;
    var startX = 0;
    var startL = 0;
    var moved = 0;
    $vp.on('pointerdown' + NAMESPACE, function (e) {
      if (e.button !== undefined && e.button !== 0) { return; }
      dragging = true;
      moved = 0;
      startX = e.clientX;
      startL = $vp[0].scrollLeft;
      $vp.addClass('is-dragging');
      try { $vp[0].setPointerCapture(e.pointerId); } catch (err) { /* ignore */ }
    });
    $vp.on('pointermove' + NAMESPACE, function (e) {
      if (!dragging) { return; }
      var dx = e.clientX - startX;
      if (Math.abs(dx) > moved) { moved = Math.abs(dx); }
      $vp[0].scrollLeft = startL - dx;
    });
    function endDrag() {
      if (!dragging) { return; }
      dragging = false;
      $vp.removeClass('is-dragging');
      if (moved > 8) {
        justDragged = true;
        setTimeout(function () { justDragged = false; }, 80);
      }
    }
    $vp.on('pointerup' + NAMESPACE + ' pointercancel' + NAMESPACE, endDrag);
    $vp.on('dragstart' + NAMESPACE, function (e) { e.preventDefault(); });

    $overlay.on('click', '.hr-room-modal__nav--prev', function () { go(idx - 1); });
    $overlay.on('click', '.hr-room-modal__nav--next', function () { go(idx + 1); });
    $overlay.on('click', '.hr-room-modal__zoom', function (e) {
      e.stopPropagation();
      if (justDragged) { return; }
      var $slide = $(this).closest('.hr-room-modal__slide');
      openLightbox($overlay, slides, Math.max(0, $slides.index($slide)));
    });
    $overlay.on('click', '.hr-room-modal__book', function () { bookRoom(room); });

    go(0, true);
  }

  Drupal.hotelReservationRoomModal = Drupal.hotelReservationRoomModal || {
    open: open,
    close: close
  };

})(jQuery, Drupal);
