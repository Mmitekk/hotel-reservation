/**
 * @file
 * Room Comparison block behavior.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  /**
   * Maximum number of rooms that can be compared.
   *
   * @type {number}
   */
  var MAX_SELECTION = 3;

  Drupal.behaviors.hotelReservationRoomComparison = {
    attach: function (context, settings) {
      var rooms = (drupalSettings.hotelReservation && drupalSettings.hotelReservation.comparisonRooms) || [];
      if (rooms.length === 0) {
        return;
      }

      // Build a lookup map by room id.
      var roomMap = {};
      rooms.forEach(function (room) {
        roomMap[room.id] = room;
      });

      var selectedIds = new Set();

      var $wrapper = $('.hr-room-comparison', context).once('hr-comparison-init');
      if ($wrapper.length === 0) {
        return;
      }

      var $count = $wrapper.find('.hr-comparison-selector__count');
      var $resetBtn = $wrapper.find('.hr-comparison-selector__reset');
      var $grid = $wrapper.find('.hr-comparison-selector__grid');
      var $tableWrapper = $wrapper.find('.hr-comparison-table-wrapper');
      var $emptyState = $wrapper.find('.hr-comparison-empty');
      var $tableHead = $wrapper.find('.hr-comparison-table thead tr');
      var $tableBody = $wrapper.find('.hr-comparison-table tbody');

      /**
       * Update UI after selection changes.
       */
      function updateUI() {
        var count = selectedIds.size;

        // Update counter.
        $count.text('Выберите номера (' + count + '/' + MAX_SELECTION + ')');

        // Show/hide reset button.
        $resetBtn.toggle(count > 0);

        // Update button states.
        $grid.find('.hr-comparison-room-btn').each(function () {
          var $btn = $(this);
          var id = parseInt($btn.attr('data-room-id'), 10);
          var isSelected = selectedIds.has(id);

          $btn.toggleClass('is-selected', isSelected);

          // Disable unselected buttons when max is reached.
          var shouldDisable = !isSelected && count >= MAX_SELECTION;
          $btn.toggleClass('is-disabled', shouldDisable);
        });

        if (count >= 2) {
          buildTable();
          $tableWrapper.show();
          $emptyState.hide();
        }
        else {
          $tableWrapper.hide();
          $emptyState.show();
        }
      }

      /**
       * Build the comparison table.
       */
      function buildTable() {
        var selectedRooms = [];
        selectedIds.forEach(function (id) {
          if (roomMap[id]) {
            selectedRooms.push(roomMap[id]);
          }
        });

        // Rebuild thead with room names.
        var headHtml = '<th class="hr-comparison-table__label-col">Параметр</th>';
        selectedRooms.forEach(function (room) {
          headHtml += '<th>' + escapeHtml(room.name) + '</th>';
        });
        $tableHead.html(headHtml);

        // Define comparison rows.
        var rows = [
          {
            label: 'Тип',
            key: 'room_type_label'
          },
          {
            label: 'Вместимость',
            key: 'capacity',
            format: function (val) { return val + ' ' + guestWord(val); }
          },
          {
            label: 'Цена за ночь',
            key: 'base_price_formatted'
          },
          {
            label: 'Удобства',
            key: 'amenities_string'
          },
          {
            label: 'Описание',
            key: 'description',
            format: function (val) {
              if (!val) { return '—'; }
              var plain = stripHtml(val);
              return plain.length > 120 ? plain.substring(0, 120) + '…' : plain;
            }
          }
        ];

        var bodyHtml = '';
        rows.forEach(function (row) {
          var values = selectedRooms.map(function (room) {
            var raw = room[row.key];
            if (row.format) {
              return row.format(raw);
            }
            return raw || '—';
          });

          // Check if all values are the same.
          var isDiff = false;
          if (values.length >= 2) {
            var first = values[0];
            for (var i = 1; i < values.length; i++) {
              if (values[i] !== first) {
                isDiff = true;
                break;
              }
            }
          }

          var rowClass = 'hr-comparison-table__row' + (isDiff ? ' hr-comparison-table__row--diff' : '');

          bodyHtml += '<tr class="' + rowClass + '">';
          bodyHtml += '<td class="hr-comparison-table__label">' + escapeHtml(row.label) + '</td>';
          values.forEach(function (val) {
            bodyHtml += '<td>' + escapeHtml(String(val)) + '</td>';
          });
          bodyHtml += '</tr>';
        });

        $tableBody.html(bodyHtml);
      }

      /**
       * Return the correct Russian word for guests.
       *
       * @param {number} n
       *   The number of guests.
       *
       * @return {string}
       */
      function guestWord(n) {
        var abs = Math.abs(n) % 100;
        var lastDigit = abs % 10;
        if (abs > 10 && abs < 20) {
          return 'гостей';
        }
        if (lastDigit > 1 && lastDigit < 5) {
          return 'гостя';
        }
        if (lastDigit === 1) {
          return 'гость';
        }
        return 'гостей';
      }

      /**
       * Escape HTML special characters.
       *
       * @param {string} str
       *   The string to escape.
       *
       * @return {string}
       */
      function stripHtml(html) {
        if (!html) return '';
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        var text = tmp.textContent || tmp.innerText || '';
        return text.replace(/\s+/g, ' ').trim();
      }

      function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
      }

      // Click handler for room buttons.
      $grid.on('click', '.hr-comparison-room-btn', function () {
        var $btn = $(this);
        var id = parseInt($btn.attr('data-room-id'), 10);

        if (selectedIds.has(id)) {
          selectedIds.delete(id);
        }
        else {
          if (selectedIds.size >= MAX_SELECTION) {
            return;
          }
          selectedIds.add(id);
        }

        updateUI();
      });

      // Reset button.
      $resetBtn.on('click', function (e) {
        e.preventDefault();
        selectedIds.clear();
        updateUI();
      });

      // Initial state.
      updateUI();
    }
  };

})(jQuery, Drupal, drupalSettings);
