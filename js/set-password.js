/**
 * @file
 * Password setup page for freshly created guest accounts.
 *
 * Reads reservation_id/token/api URL from the .hr-set-password container
 * data attributes and posts to the set-password JSON endpoint.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.hotelReservationSetPassword = {
    attach: function (context) {
      var $box = $('.hr-set-password', context).once('hr-set-password');
      if ($box.length === 0) {
        return;
      }

      var reservationId = parseInt($box.attr('data-reservation-id'), 10);
      var token = $box.attr('data-token') || '';
      var apiUrl = $box.attr('data-api-url') || '/api/hotel-reservation/set-password';
      var $errors = $box.find('.hr-success-password__errors');

      $box.on('click', '.hr-set-password__btn', function () {
        var $btn = $(this);
        $errors.empty();
        var p1 = $box.find('.hr-success-password__input').val() || '';
        var p2 = $box.find('.hr-success-password__confirm').val() || '';
        if (p1.length < 8) {
          $errors.html('<div class="hr-error">' + Drupal.t('Пароль должен содержать не менее 8 символов.') + '</div>');
          return;
        }
        if (p1 !== p2) {
          $errors.html('<div class="hr-error">' + Drupal.t('Пароли не совпадают.') + '</div>');
          return;
        }
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="hr-spinner"></span>' + Drupal.t('Сохранение...'));
        $.ajax({
          url: apiUrl,
          method: 'POST',
          contentType: 'application/json',
          dataType: 'json',
          data: JSON.stringify({
            reservation_id: reservationId,
            token: token,
            password: p1
          }),
          success: function (response) {
            $box.html('<div class="hr-success-password__done">' + Drupal.checkPlain(response.message || Drupal.t('Пароль сохранён.')) + '</div>');
            if (response.redirect) {
              setTimeout(function () {
                window.location.href = response.redirect;
              }, 1200);
            }
          },
          error: function (xhr) {
            var msg = Drupal.t('Не удалось сохранить пароль. Попробуйте ещё раз.');
            try {
              var data = JSON.parse(xhr.responseText);
              if (data.message) {
                msg = data.message;
              }
            }
            catch (e) { /* keep default */ }
            $errors.html('<div class="hr-error">' + Drupal.checkPlain(msg) + '</div>');
          },
          complete: function () {
            $btn.prop('disabled', false).html(originalText);
          }
        });
      });
    }
  };

})(jQuery, Drupal);
