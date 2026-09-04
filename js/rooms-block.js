/**
 * @file
 * "Наши номера" — расширенная карточка в модальном окне со слайдером.
 */
(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.hotelReservationRoomsBlock = {
    attach: function (context, settings) {
      var data = (drupalSettings.hotelReservation && drupalSettings.hotelReservation.roomsDetail) || {};
      var ids = Object.keys(data);
      if (!ids.length) {
        return;
      }
      var modalWidth = (drupalSettings.hotelReservation && drupalSettings.hotelReservation.roomModalWidth) || 80;

      var $grid = $('.hr-rooms-grid', context).once('hr-rooms-modal');
      if (!$grid.length) {
        return;
      }

      function esc(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str === undefined || str === null ? '' : str)));
        return div.innerHTML;
      }

      function guestWord(n) {
        var abs = Math.abs(n) % 100;
        var d = abs % 10;
        if (abs > 10 && abs < 20) { return 'гостей'; }
        if (d > 1 && d < 5) { return 'гостя'; }
        if (d === 1) { return 'гость'; }
        return 'гостей';
      }

      function openModal(room) {
        closeModal();
        var slides = room.slides || [];
        var html = '<div class="hr-room-modal-overlay" role="dialog" aria-modal="true">';
        html += '<div class="hr-room-modal" style="--hr-room-modal-width:' + parseInt(modalWidth, 10) + '%">';
        html += '<button type="button" class="hr-room-modal__close" aria-label="Закрыть">✕</button>';

        if (slides.length) {
          html += '<div class="hr-room-modal__slider"><div class="hr-room-modal__slides">';
          slides.forEach(function (s, i) {
            html += '<div class="hr-room-modal__slide' + (i === 0 ? ' is-active' : '') + '" data-index="' + i + '">';
            html += '<img src="' + esc(s.url) + '" alt="' + esc(s.alt || room.name) + '" loading="lazy"></div>';
          });
          html += '</div>';
          if (slides.length > 1) {
            html += '<button type="button" class="hr-room-modal__nav hr-room-modal__nav--prev" aria-label="Назад">‹</button>';
            html += '<button type="button" class="hr-room-modal__nav hr-room-modal__nav--next" aria-label="Вперёд">›</button>';
            html += '<div class="hr-room-modal__dots">';
            slides.forEach(function (s, i) {
              html += '<button type="button" class="hr-room-modal__dot' + (i === 0 ? ' is-active' : '') + '" data-index="' + i + '" aria-label="Слайд ' + (i + 1) + '"></button>';
            });
            html += '</div>';
          }
          html += '</div>';
        }

        html += '<div class="hr-room-modal__body">';
        html += '<span class="hr-room-card__type-badge" style="background:' + esc(room.type_color || '#6b7280') + ';color:#fff">' + esc(room.type_label || '') + '</span>';
        html += '<h3 class="hr-room-modal__name">' + esc(room.name) + '</h3>';
        html += '<div class="hr-room-modal__meta"><span>👥 До ' + parseInt(room.capacity, 10) + ' ' + esc(guestWord(parseInt(room.capacity, 10))) + '</span>';
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
        html += '</div></div></div>';

        var $overlay = $(html);
        $('body').append($overlay).css('overflow', 'hidden');
        $overlay.find('.hr-room-modal__close').on('click', closeModal);
        $overlay.on('click', function (e) {
          if (e.target === this) { closeModal(); }
        });
        $(document).on('keydown.hrRoomModal', function (e) {
          if (e.key === 'Escape') { closeModal(); }
        });

        var idx = 0;
        function show(i) {
          idx = (i + slides.length) % slides.length;
          $overlay.find('.hr-room-modal__slide').removeClass('is-active').eq(idx).addClass('is-active');
          $overlay.find('.hr-room-modal__dot').removeClass('is-active').eq(idx).addClass('is-active');
        }
        $overlay.on('click', '.hr-room-modal__nav--prev', function () { show(idx - 1); });
        $overlay.on('click', '.hr-room-modal__nav--next', function () { show(idx + 1); });
        $overlay.on('click', '.hr-room-modal__dot', function () { show(parseInt($(this).attr('data-index'), 10)); });
      }

      function closeModal() {
        $('.hr-room-modal-overlay').remove();
        $('body').css('overflow', '');
        $(document).off('keydown.hrRoomModal');
      }

      $grid.on('click', '.hr-room-card--clickable', function () {
        var id = $(this).attr('data-room-id');
        if (id && data[id]) { openModal(data[id]); }
      });
      $grid.on('keydown', '.hr-room-card--clickable', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          var id = $(this).attr('data-room-id');
          if (id && data[id]) { openModal(data[id]); }
        }
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
