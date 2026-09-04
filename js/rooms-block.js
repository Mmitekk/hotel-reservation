/**
 * @file
 * "Наши номера" — клик по карточке открывает общую модалку
 * (Drupal.hotelReservationRoomModal из hotel_reservation/room-modal).
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
      var modalWidth = (drupalSettings.hotelReservation && drupalSettings.hotelReservation.roomModalWidth) || 65;

      var $grid = $('.hr-rooms-grid', context).once('hr-rooms-modal');
      if (!$grid.length) {
        return;
      }

      function openRoom(id) {
        if (id && data[id] && Drupal.hotelReservationRoomModal) {
          Drupal.hotelReservationRoomModal.open(data[id], {width: modalWidth});
        }
      }

      $grid.on('click', '.hr-room-card--clickable', function () {
        openRoom($(this).attr('data-room-id'));
      });
      $grid.on('keydown', '.hr-room-card--clickable', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          openRoom($(this).attr('data-room-id'));
        }
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
