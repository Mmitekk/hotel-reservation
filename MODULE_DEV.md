# Модуль Hotel Reservation — Руководство для разработчика

> Последняя версия: 1.6.4 | Composer: `mmitekk/hotel-reservation` | Репозиторий: [GitHub](https://github.com/Mmitekk/hotel-reservation)

---

## Содержание

1. [Общая информация](#1-общая-информация)
2. [Установка и обновление](#2-установка-и-обновление)
3. [Структура модуля](#3-структура-модуля)
4. [Сущности (БД)](#4-сущности-бд)
5. [Конфигурация](#5-конфигурация)
6. [Маршруты](#6-маршруты)
7. [API-эндпоинты](#7-api-эндпоинты)
8. [Блоки](#8-блоки)
9. [Хуки модуля](#9-хуки-модуля)
10. [Глобальные функции](#10-глобальные-функции)
11. [Шаблоны Twig](#11-шаблоны-twig)
12. [JS-файлы](#12-js-файлы)
13. [CSS-файлы](#13-css-файлы)
14. [Письма](#14-письма)
15. [Статусы бронирований](#15-статусы-бронирований)
16. [Кеширование](#16-кеширование)
17. [Права доступа](#17-права-доступа)
18. [Релизы](#18-релизы)
19. [Известные ограничения](#19-известные-ограничения)

---

## 1. Общая информация

| Параметр | Значение |
|---|---|
| Машинное имя | `hotel_reservation` |
| Пространство имён | `Drupal\hotel_reservation\` |
| PSR-4 путь | `src/` |
| Версия Drupal | ^9.5 \|\| ^10.0 |
| PHP | >=7.4 |
| Лицензия | GPL-2.0-or-later |
| Зависимости | `drupal:datetime`, `drupal:options`, `drupal:field`, `drupal:user`, `drupal:system` |

### Возможности модуля

- Управление номерами (CRUD, типы, вместимость, цены, удобства, сортировка)
- Управление бронированиями (CRUD, статусы, фильтры, экспорт CSV)
- Динамическое ценообразование (календарь цен по датам для каждого номера)
- 3-шаговая форма бронирования на фронтенде (AJAX, модальное/страничное отображение)
- Блок галереи номеров (сетка/карусель)
- Блок сравнения номеров (интерактивная таблица 2-3 номера)
- Календарь занятости (админка)
- Дашборд со статистикой
- Аналитика (конверсия, выручка, тренды)
- Email-уведомления (админу + гостю)
- Автоистечение заявок по cron
- Полная кастомизация внешнего вида формы (цвета, скругления, тексты)

---

## 2. Установка и обновление

### Стандартная установка

```bash
composer require mmitekk/hotel-reservation
```

### Установка при проблемах с версиями PHP (Drupal 9.5.11)

```bash
composer82 require mmitekk/hotel-reservation --no-interaction --ignore-platform-reqs
```

### Обновление до последней версии

```bash
composer update mmitekk/hotel-reservation --no-interaction --ignore-platform-reqs -W
```

Ключ `-W` (`--with-all-dependencies`) обязателен для подтягивания нового тега.

### После установки/обновления

```bash
drush cr           # Сбросить кеш
drush updb         # Запустить обновления БД (если есть hook_update_N)
drush entity-updates  # Применить изменения сущностей (при обновлении полей)
```

### Удаление

```bash
drush pm-uninstall hotel_reservation
composer remove mmitekk/hotel-reservation
```

> **Важно:** `hook_uninstall()` удаляет только конфигурацию. Таблицы `hr_room`, `hr_reservation`, `hr_room_pricing` остаются в БД. Для полного удаления выполните `drush sql:query "DROP TABLE hr_room, hr_reservation, hr_room_pricing;"` перед удалением модуля.

---

## 3. Структура модуля

```
hotel_reservation.info.yml         # Метаданные модуля, версия, зависимости
hotel_reservation.module           # Хуки: help, theme, cron, mail, preprocess_block
drupal_reservation.install          # install/uninstall хуки
drupal_reservation.routing.yml      # 11 маршрутов
hotel_reservation.permissions.yml # 3 права доступа
hotel_reservation.links.menu.yml   # 6 пунктов меню админки
hotel_reservation.links.action.yml # 1 локальное действие
hotel_reservation.links.task.yml   # 6 вкладок (табов)
drupal_reservation.libraries.yml   # 6 библиотек CSS/JS
hotel_reservation.schema.yml       # Схема конфигурации
composer.json                       # Composer-пакет
README.md                          # Пользовательская документация
MODULE_DEV.md                      # ← Этот файл

src/
  Entity/
    Room.php                       # Сущность «Номер» (hr_room)
    Reservation.php                # Сущность «Бронирование» (hr_reservation)
    RoomPricing.php                 # Сущность «Цена номера» (hr_room_pricing)
  Controller/
    HotelReservationController.php  # Календарь, цены, смена статуса
    DashboardController.php         # Дашборд, экспорт CSV
    ApiController.php               # JSON API для фронтенда (3 эндпоинта)
    AnalyticsController.php         # Страница аналитики
  Form/
    SettingsForm.php                # Форма настроек модуля
    RoomForm.php                    # Форма создания/редактирования номера
    ReservationForm.php             # Форма бронирования (с AJAX-расчётом цены)
  Plugin/Block/
    BookingFormBlock.php            # Блок формы бронирования
    RoomsBlock.php                  # Блок галереи номеров
    RoomComparisonBlock.php         # Блок сравнения номеров
  RoomListBuilder.php               # Таблица номеров в админке
  ReservationListBuilder.php        # Таблица бронирований в админке (с фильтрами)

templates/
  dashboard.html.twig               # Шаблон дашборда
  analytics.html.twig               # Шаблон аналитики
  admin-calendar.html.twig          # Шаблон календаря занятости
  reservation-view.html.twig        # Шаблон печати ваучера

css/
  booking-form.css                  # ~890 строк — стили формы бронирования
  rooms-block.css                   # Стили галереи номеров
  room-comparison.css               # Стили сравнения номеров
  admin-global.css                  # Общие админские стили + бейджи
  admin-calendar.css                # Стили календаря
  room-pricing.css                  # Стили таблицы цен
  dashboard.css                     # Стили дашборда
  analytics.css                     # Стили аналитики

js/
  booking-form.js                   # ~570 строк — логика 3-шаговой формы
  room-comparison.js                # ~230 строк — логика сравнения
```

---

## 4. Сущности (БД)

### 4.1 Номер — `hr_room`

**Класс:** `Drupal\hotel_reservation\Entity\Room`
**Таблица:** `hr_room`
**Аннотация:** `@ContentEntityType`

| Поле | Тип | Обязательное | По умолчанию | Описание |
|---|---|---|---|---|
| `id` | integer (автоинкремент) | да | — | Primary key |
| `uuid` | uuid | да | авто | UUID |
| `name` | string (255) | да | `''` | Название номера |
| `description` | text_long | нет | `''` | Описание (может содержать HTML от WYSIWYG) |
| `room_type` | list_string | нет | `standard` | Тип номера |
| `capacity` | integer (unsigned) | да | `2` | Макс. гостей (1–20) |
| `base_price` | decimal (10,2) | да | `0.00` | Базовая цена за ночь |
| `amenities` | string_long | нет | `''` | Удобства через запятую |
| `status` | boolean | да | `TRUE` | Опубликован (доступен для бронирования) |
| `sort_weight` | integer (signed) | нет | `0` | Порядок сортировки |
| `created` | created | авто | — | Дата создания |
| `changed` | changed | авто | — | Дата изменения |

**Варианты `room_type`:**

| Ключ | Метка (RU) | Цвет в карточках |
|---|---|---|
| `standard` | Стандарт | `#6b7280` |
| `superior` | Супериор | `#0ea5e9` |
| `deluxe` | Делюкс | `#8b5cf6` |
| `suite` | Сьют | `#f59e0b` |
| `apartment` | Апартаменты | `#10b981` |
| `villa` | Вилла | `#ec4899` |
| `family` | Семейный | `#06b6d4` |
| `economy` | Эконом | `#64748b` |

> **Цвета определены в двух местах:** константы `ROOM_TYPE_LABELS` и `ROOM_TYPE_COLORS` в `RoomsBlock.php`. При добавлении нового типа обновите оба массива.

**Основные методы:**

```php
$room->getName() / setName($name)
$room->getDescription() / setDescription($desc)  // Возвращает HTML от WYSIWYG!
$room->getCapacity() / setCapacity($n)
$room->getBasePrice() / setBasePrice($price)
$room->getAmenities() / setAmenities($str)    // Строка: "Wi-Fi, ТВ, Парковка"
$room->getSortWeight() / setSortWeight($w)
$room->isPublished()                           // Из EntityPublishedTrait
$room->getCreatedTime() / setCreatedTime($ts)
```

### 4.2 Бронирование — `hr_reservation`

**Класс:** `Drupal\hotel_reservation\Entity\Reservation`
**Таблица:** `hr_reservation`

| Поле | Тип | Обязательное | По умолчанию | Описание |
|---|---|---|---|---|
| `id` | integer (автоинкремент) | да | — | Primary key |
| `uuid` | uuid | да | авто | UUID |
| `room_id` | entity_reference → hr_room | да | — | Связанный номер |
| `check_in` | datetime (date) | да | — | Дата заезда |
| `check_out` | datetime (date) | да | — | Дата выезда |
| `guest_name` | string (255) | да | `''` | Имя гостя |
| `guest_phone` | string (50) | да | `''` | Телефон |
| `guest_email` | string (255) | нет | `''` | Email |
| `guest_count` | integer (unsigned) | нет | `1` | Количество гостей |
| `status` | list_string (32) | да | `pending` | Статус |
| `total_price` | decimal (10,2) | нет | `0.00` | Итоговая сумма |
| `notes` | text_long | нет | `''` | Заметки гостя |
| `admin_notes` | text_long | нет | `''` | Заметки администратора |
| `created` | created | авто | — | Дата создания |
| `changed` | changed | авто | — | Дата изменения |

**Основные методы:**

```php
$reservation->label()                           // Возвращает guest_name
$reservation->getStatusLabel()                   // Русская метка статуса
$reservation->getStatusOptions()                 // Статический: все статусы
$reservation->getRoom()                          // Объект Room
$reservation->getCheckInDate() / getCheckOutDate()  // Объекты DrupalDateTime
$reservation->getTotalPrice() / setTotalPrice($p)
$reservation->getCreatedTime() / setCreatedTime($ts)
```

### 4.3 Цена номера — `hr_room_pricing`

**Класс:** `Drupal\hotel_reservation\Entity\RoomPricing`
**Таблица:** `hr_room_pricing`

| Поле | Тип | Обязательное | Описание |
|---|---|---|---|
| `id` | integer (авто) | да | Primary key |
| `uuid` | uuid | да | UUID |
| `room_id` | entity_reference → hr_room | да | Номер |
| `date` | datetime (date) | да | Дата |
| `price` | decimal (10,2) | да | Цена на эту дату |
| `created` | created | авто | Дата создания |

> Эта сущность **не имеет своих форм и страниц**. Управляется только через календарь цен (роут `hotel_reservation.room_pricing`). Если цена равна базовой — запись удаляется.

**Методы:** `getRoom()`, `getDate()`, `getPrice()`, `setPrice($p)`, `getCreatedTime()`

---

## 5. Конфигурация

**Ключ:** `hotel_reservation.settings`
**Форма:** `/admin/config/hotel-reservation/settings`

### Группы настроек

**Информация об отеле:**
| Ключ | Тип | По умолчанию | Описание |
|---|---|---|---|
| `hotel_name` | string | (имя сайта) | Название отеля |
| `currency_symbol` | string | `₽` | Символ валюты |
| `currency_code` | string | `RUB` | Код валюты (ISO) |

**Правила бронирования:**
| Ключ | Тип | По умолчанию | Описание |
|---|---|---|---|
| `min_stay_nights` | integer (1–9999) | `1` | Минимум ночей |
| `max_stay_nights` | integer (1–9999) | `30` | Максимум ночей |
| `check_in_time` | string (HH:MM) | `14:00` | Время заезда |
| `check_out_time` | string (HH:MM) | `12:00` | Время выезда |
| `booking_conditions` | text | (6 пунктов) | Условия бронирования |

**Уведомления:**
| Ключ | Тип | По умолчанию | Описание |
|---|---|---|---|
| `enable_admin_notification` | boolean | `TRUE` | Уведомления администратору |
| `admin_notification_email` | email | — | Email администратора |
| `enable_guest_confirmation` | boolean | `TRUE` | Уведомления гостю |

**Автоматизация:**
| Ключ | Тип | По умолчанию | Описание |
|---|---|---|---|
| `reservation_expiration_hours` | integer (1–720) | `24` | Через сколько часов заявка истекает |

**Дизайн формы бронирования:**
| Ключ | Тип | По умолчанию | Описание |
|---|---|---|---|
| `form_title` | string | `Бронирование` | Заголовок формы |
| `form_subtitle` | string | — | Подзаголовок |
| `form_button_text` | string | `Забронировать` | Текст кнопки |
| `form_primary_color` | string | `#d97706` | Основной цвет |
| `form_background_color` | string | `#ffffff` | Цвет фона |
| `form_text_color` | string | `#1a1a2e` | Цвет текста |
| `form_border_radius` | integer (0–30) | `10` | Скругление (px) |
| `form_success_title` | string | — | Заголовок успеха |
| `form_success_text` | string | — | Текст успеха (плейсхолдер `@id`) |

**Получение конфига в коде:**

```php
$config = \Drupal::config('hotel_reservation.settings');
$currency = $config->get('currency_symbol') ?: '₽';
$hotelName = $config->get('hotel_name') ?: \Drupal::config('system.site')->get('name');
```

---

## 6. Маршруты

### 6.1 Админские маршруты (требуют `administer hotel reservation`)

| Роут | Путь | Контроллер | Описание |
|---|---|---|---|
| `hotel_reservation.dashboard` | `/admin/hotel-reservation/dashboard` | `DashboardController::dashboard` | Дашборд |
| `hotel_reservation.analytics` | `/admin/hotel-reservation/analytics` | `AnalyticsController::analytics` | Аналитика |
| `hotel_reservation.settings` | `/admin/config/hotel-reservation/settings` | `SettingsForm` | Настройки |
| `hotel_reservation.calendar` | `/admin/hotel-reservation/calendar/{month}/{year}` | `HotelReservationController::calendar` | Календарь занятости |
| `hotel_reservation.room_pricing` | `/admin/hotel-reservation/rooms/{hr_room}/pricing/{month}/{year}` | `HotelReservationController::roomPricing` | Календарь цен номера |
| `hotel_reservation.room_pricing_save` | `/admin/hotel-reservation/rooms/{hr_room}/pricing/save` | `HotelReservationController::roomPricingSave` | Сохранение цен (POST) |
| `hotel_reservation.reservation_status` | `/admin/hotel-reservation/reservations/{hr_reservation}/status/{status}` | `HotelReservationController::changeReservationStatus` | Смена статуса (CSRF) |
| `hotel_reservation.export_csv` | `/admin/hotel-reservation/reservations/export` | `DashboardController::exportCsv` | Экспорт CSV |

### 6.2 Маршруты сущностей (автогенерация)

**Номера (`hr_room`):**
| Роут | Путь |
|---|---|
| `entity.hr_room.collection` | `/admin/hotel-reservation/rooms` |
| `entity.hr_room.add_form` | `/admin/hotel-reservation/rooms/add` |
| `entity.hr_room.edit_form` | `/admin/hotel-reservation/rooms/{hr_room}/edit` |
| `entity.hr_room.delete_form` | `/admin/hotel-reservation/rooms/{hr_room}/delete` |

**Бронирования (`hr_reservation`):**
| Роут | Путь |
|---|---|
| `entity.hr_reservation.collection` | `/admin/hotel-reservation/reservations` |
| `entity.hr_reservation.canonical` | `/admin/hotel-reservation/reservations/{hr_reservation}` |
| `entity.hr_reservation.edit_form` | `/admin/hotel-reservation/reservations/{hr_reservation}/edit` |
| `entity.hr_reservation.delete_form` | `/admin/hotel-reservation/reservations/{hr_reservation}/delete` |

---

## 7. API-эндпоинты

Все API-эндпоинты требуют только `_permission: 'access content'` (не требуют авторизации).

### 7.1 Проверка доступности

```
POST /api/hotel-reservation/check-availability
Content-Type: application/json
```

**Запрос:**
```json
{
  "check_in": "2026-07-15",
  "check_out": "2026-07-18",
  "guest_count": 2
}
```

**Успешный ответ (200):**
```json
{
  "success": true,
  "rooms": [
    {
      "id": 1,
      "name": "Стандартный номер",
      "description": "Описание номера...",
      "capacity": 3,
      "base_price": "5000.00",
      "total_price": "15000.00",
      "nights": 3,
      "available": true,
      "amenities": "Wi-Fi, ТВ"
    }
  ],
  "check_in": "2026-07-15",
  "check_out": "2026-07-18",
  "nights": 3,
  "guest_count": 2
}
```

**Ошибки (400):** `Неверные данные JSON`, `Укажите check_in и check_out`, `Неверный формат даты`, `Дата выезда должна быть позже`, `Мин/макс количество ночей: N`

### 7.2 Создание бронирования

```
POST /api/hotel-reservation/submit
Content-Type: application/json
```

**Запрос:**
```json
{
  "room_id": 1,
  "check_in": "2026-07-15",
  "check_out": "2026-07-18",
  "guest_name": "Иван Иванов",
  "guest_phone": "+7 (999) 123-45-67",
  "guest_email": "ivan@example.com",
  "guest_count": 2,
  "notes": "Нужен额外 подушка"
}
```

**Успешный ответ (200):**
```json
{
  "success": true,
  "message": "Бронирование создано. Ваша заявка ожидает подтверждения.",
  "reservation_id": 42
}
```

**Ошибки:** 400 (валидация), 404 (номер не найден), 409 (номер занят), 500 (ошибка создания)

### 7.3 Цены по датам

```
GET /api/hotel-reservation/room-prices/{room_id}?check_in=2026-07-15&check_out=2026-07-18
```

**Ответ:**
```json
{
  "success": true,
  "room_id": 1,
  "room_name": "Стандартный номер",
  "base_price": "5000.00",
  "nights": 3,
  "total_price": "16000.00",
  "formatted_total": "16,000.00 ₽",
  "currency": "₽",
  "daily_prices": [
    {"date": "2026-07-15", "price": "5000.00", "formatted": "5,000.00 ₽"},
    {"date": "2026-07-16", "price": "6000.00", "formatted": "6,000.00 ₽"},
    {"date": "2026-07-17", "price": "5000.00", "formatted": "5,000.00 ₽"}
  ]
}
```

---

## 8. Блоки

### 8.1 Форма бронирования — `hotel_reservation_booking_form`

**Конфигурация (настройки блока):**

| Ключ | Тип | По умолчанию | Описание |
|---|---|---|---|
| `display_mode` | `modal` / `inline` | `modal` | Как отображается форма |
| `show_conditions` | boolean | `TRUE` | Показывать условия бронирования |
| `use_page_content_section` | boolean | `FALSE` | Обернуть в `.page-content-section` |
| `use_block_spacing` | boolean | `FALSE` | Добавить отступы 5rem сверху/снизу |
| `preview_align` | `left` / `center` / `right` | `center` | Выравнивание превью (модальный режим) |

**Поведение:**
- В режиме `modal` рендерит карточку-превью и кнопку «Забронировать», по клику открывается модальное окно с 3-шаговой формой
- В режиме `inline` форма рендерится прямо на странице
- Все настройки передаются в JS через `drupalSettings.hotelReservation`
- Цвета из конфигурации применяются как CSS-переменные (`--hr-primary`, `--hr-primary-dark`, и т.д.)

**CSS-переменные, которые устанавливает JS:**
```
--hr-primary          — основной цвет
--hr-primary-dark     — затемнённый (для градиентов кнопок)
--hr-primary-light    — осветлённый
--hr-primary-bg       — основной цвет с прозрачностью 0.08
--hr-primary-border   — основной цвет с прозрачностью 0.25
--hr-btn-text         — цвет текста на кнопках (#ffffff)
--hr-bg               — цвет фона формы
--hr-text             — цвет текста
--hr-radius           — скругление
```

### 8.2 Галерея номеров — `hotel_reservation_rooms`

**Конфигурация:**

| Ключ | Тип | По умолчанию | Описание |
|---|---|---|---|
| `limit` | integer (1–50) | `8` | Сколько номеров показывать |
| `show_title` | boolean | `TRUE` | Показывать название |
| `show_description` | boolean | `TRUE` | Показывать описание |
| `show_price` | boolean | `TRUE` | Показывать цену |
| `show_amenities` | boolean | `TRUE` | Показывать удобства |
| `layout` | `grid` / `carousel` | `grid` | Макет |
| `use_page_content_section` | boolean | `FALSE` | Обернуть в `.page-content-section` |
| `use_block_spacing` | boolean | `FALSE` | Добавить отступы 5rem |

**Особенности:**
- Описания номеров проходят через `strip_tags()` + `Html::escape()` (HTML из WYSIWYG удаляется)
- Описание обрезается до 120 символов
- Карточки сортируются по `sort_weight` ASC
- Карточки содержат цветной бейдж типа номера (цвета захардкожены в `ROOM_TYPE_COLORS`)

### 8.3 Сравнение номеров — `hotel_reservation_room_comparison`

**Конфигурация:**

| Ключ | Тип | По умолчанию | Описание |
|---|---|---|---|
| `use_page_content_section` | boolean | `FALSE` | Обернуть в `.page-content-section` |
| `use_block_spacing` | boolean | `FALSE` | Добавить отступы 5rem |

**Особенности:**
- Данные номеров передаются в JS через `drupalSettings.hotelReservation.comparisonRooms`
- Описания проходят через `strip_tags()` перед передачей в JS
- Пользователь выбирает 2–3 номера, таблица строится динамически
- Строки с разными значениями подсвечиваются классом `.hr-comparison-table__row--diff`

---

## 9. Хуки модуля

### `hook_help()`
Подсказки на страницах `help.page.hotel_reservation` и `hotel_reservation.settings`.

### `hook_theme()`
6 тем-хуков (см. [Шаблоны Twig](#11-шаблоны-twig)).

### `hook_cron()`
Автоматически истекает (статус `expired`) заявки в статусе `pending` старше `reservation_expiration_hours` часов.

### `hook_mail($key, &$message, $params)`
Два ключа: `reservation_confirmation` и `reservation_admin_notification`.

### `hook_preprocess_block(&$variables)`
Для 3 блоков модуля проверяет конфигурацию и добавляет CSS-классы к обёртке блока:
- `use_page_content_section` → класс `.page-content-section`
- `use_block_spacing` → класс `.hr-block-spacing` (margin-top/bottom: 5rem)

```php
$our_blocks = [
  'hotel_reservation_booking_form',
  'hotel_reservation_rooms',
  'hotel_reservation_room_comparison',
];
```

---

## 10. Глобальные функции

Определены в `hotel_reservation.module`. Используются контроллерами и формами.

### `hotel_reservation_get_available_rooms($check_in, $check_out, $guest_count)`

```php
/**
 * @param string $check_in    Дата заезда (Y-m-d)
 * @param string $check_out   Дата выезда (Y-m-d)
 * @param int    $guest_count Количество гостей
 *
 * @return \Drupal\hotel_reservation\Entity\Room[] Массив доступных номеров, ключ = ID
 */
```

Логика:
1. Запрашивает опубликованные номера с `capacity >= $guest_count`
2. Ищет пересекающиеся бронирования (статусы: `pending`, `confirmed`, `checked_in`)
3. Исключает занятые номера
4. Возвращает массив доступных `Room` сущностей

### `hotel_reservation_calculate_price($room_id, $check_in, $check_out)`

```php
/**
 * @param int    $room_id  ID номера
 * @param string $check_in Дата заезда (Y-m-d)
 * @param string $check_out Дата выезда (Y-m-d)
 *
 * @return array [
 *   'total'         => float,  // Общая сумма
 *   'nights'        => int,    // Количество ночей
 *   'daily_prices'  => array,  // ['Y-m-d' => float]
 *   'base_price'    => float,  // Базовая цена номера
 * ]
 */
```

Логика:
1. Загружает базовую цену номера
2. Загружает все `hr_room_pricing` для этого номера в диапазоне дат
3. Итерирует по каждой ночи: если есть индивидуальная цена — использует её, иначе базовую
4. Возвращает детализированный расчёт

---

## 11. Шаблоны Twig

### `templates/dashboard.html.twig`
**Переменные:** `stats`, `pending_reservations`, `upcoming_checkins`, `currency`, `weekly_revenue`, `weekly_total`, `export_url`

Содержит: карточки статистики (4 шт), столбчатая диаграмма выручки за 7 дней, таблица ожидающих бронирований, список ближайших заездов.

### `templates/analytics.html.twig`
**Переменные:** `stats`, `status_distribution`, `room_revenue`, `top_room`, `avg_stay`, `weekly_data`, `monthly_data`, `currency`, `conversion_rate`, `avg_booking_value`

Содержит: KPI-карточки, распределение по статусам, ранжирование номеров по выручке, еженедельная двойная диаграмма, таблица помесячных трендов.

### `templates/admin-calendar.html.twig`
**Переменные:** `rooms`, `days`, `month_name`, `year`, `prev_url`, `current_url`, `next_url`, `month_selector`

Содержит: навигация по месяцам (выпадающий список 24 месяца), легенда статусов, таблица-календарь (номера × дни).

### `templates/reservation-view.html.twig`
**Переменные:** `reservation`, `room`, `currency`, `hotel_name`, `check_in_time`, `check_out_time`

Содержит: ваучер бронирования для печати. 2-колоночная вёрстка с данными гостя и бронирования. Включает `@media print` для печати.

---

## 12. JS-файлы

### `js/booking-form.js` (~570 строк)

**Drupal behavior:** `hotelReservationBookingForm`

**Конфигурация из `drupalSettings.hotelReservation`:**
- `apiCheckUrl` — URL проверки доступности
- `apiSubmitUrl` — URL создания бронирования
- `primaryColor`, `backgroundColor`, `textColor`, `borderRadius`
- `displayMode`, `showConditions`, `previewAlign`
- `checkInTime`, `checkOutTime`, `hotelName`
- `formTitle`, `formSubtitle`, `formButtonText`
- `successTitle`, `successText`

**Ключевые функции:**
- `hexToRgb()`, `hexToRgba()`, `darkenColor()`, `lightenColor()` — утилиты цвета
- `pluralRu(n, one, few, many)` — русские склонения
- `showStep(n)` — переключение шагов формы (1=поиск, 2=выбор, 3=бронирование, 4=успех)
- AJAX-запросы для поиска и отправки бронирования
- Маска телефона: `+7 (XXX) XXX-XX-XX`, автозамена `8` → `7`
- Счётчик гостей: +/-, диапазон 1–20
- Модальное окно: открытие/закрытие, Escape, клик по оверлею

### `js/room-comparison.js` (~230 строк)

**Drupal behavior:** `hotelReservationRoomComparison`

**Данные из `drupalSettings.hotelReservation.comparisonRooms`:**
Массив объектов: `{id, name, room_type_label, capacity, base_price_formatted, amenities, amenities_string, description}`

**Ключевые функции:**
- Выбор 2–3 номеров, динамическое построение таблицы
- Подсветка различий
- Сброс выбора

---

## 13. CSS-файлы

| Файл | Назначение |
|---|---|
| `css/booking-form.css` | Форма бронирования, модальное окно, превью-карточка, шаги, карточки номеров, прайс-брейкдаун, адаптив (520px, 380px) |
| `css/rooms-block.css` | Галерея номеров: сетка, карточки, бейджи типов, hover-эффекты |
| `css/room-comparison.css` | Сравнение: кнопки выбора, таблица, подсветка различий |
| `css/admin-global.css` | Общие стили админки, бейджи статусов (`.badge-*`), бейджи типов номеров |
| `css/admin-calendar.css` | Календарь: сетка, навигация, ячейки (сегодня, выходные, прошедшие), индикаторы |
| `css/room-pricing.css` | Таблица цен: форма, разница цен (`.price-increase`, `.price-decrease`) |
| `css/dashboard.css` | Дашборд: карточки, столбчатая диаграмма, таблицы |
| `css/analytics.css` | Аналитика: KPI, диаграммы, таблицы трендов |

---

## 14. Письма

### Ключ `reservation_confirmation`
- **Тема:** «Подтверждение бронирования — {hotel}»
- **Получатель:** email гостя
- **Отправляется при:** подтверждении заявки (статус → `confirmed`)
- **Переменные:** `@hotel`, `@guest`, `@room`, `@check_in`, `@check_out`, `@count`, `@total`, `@currency`

### Ключ `reservation_admin_notification`
- **Тема:** «Новое бронирование от {guest}»
- **Получатель:** `admin_notification_email` из настроек
- **Отправляется при:** новом бронировании (через API или админку), отмене бронирования
- **Переменные:** `@guest`, `@email`, `@phone`, `@room`, `@check_in`, `@check_out`, `@count`, `@total`, `@currency`, `@notes`

**Управление:** включается/выключается через настройки `enable_admin_notification` и `enable_guest_confirmation`.

---

## 15. Статусы бронирований

| Статус | Константа | Метка (RU) | Буква в календаре |
|---|---|---|---|
| `pending` | `STATUS_PENDING` | Ожидает | **П** |
| `confirmed` | `STATUS_CONFIRMED` | Подтверждено | **П** (другой цвет) |
| `checked_in` | `STATUS_CHECKED_IN` | Заселён | **З** |
| `checked_out` | `STATUS_CHECKED_OUT` | Выселён | **В** |
| `cancelled` | `STATUS_CANCELLED` | Отменено | **Х** |
| `expired` | `STATUS_EXPIRED` | Истёк | **И** |

### Допустимые переходы

```
pending    → confirmed, cancelled, expired
confirmed  → checked_in, cancelled
checked_in → checked_out
checked_out → (нет)
cancelled  → (нет)
expired    → (нет)
```

Переходы проверяются в `HotelReservationController::changeReservationStatus()`. Недопустимый переход вызывает `AccessDeniedHttpException`.

---

## 16. Кеширование

### Cache tags

| Тег | Где инвалидируется |
|---|---|
| `hr_room_list` | При CRUD номеров, используется блоками Rooms и Comparison |
| `hr_room:{id}` | При изменении конкретного номера |
| `hr_reservation_list` | При CRUD бронирований |
| `hr_room_pricing_list` | При изменении цен, используется формой бронирования |
| `hotel_reservation_settings` | При сохранении настроек |

### Max-age
Все блоки и страницы модуля используют `max-age: 0` (без кеширования по времени).

---

## 17. Права доступа

| Право | Ограничено? | Использование |
|---|---|---|
| `administer hotel reservation` | Да (admin) | Все админские страницы: номера, бронирования, настройки, календарь, аналитика |
| `view hotel reservation` | Нет | Определено, но **не используется** в маршрутах |
| `create hotel reservation` | Нет | Определено, но **не используется** в маршрутах |

> API-эндпоинты (`/api/hotel-reservation/*`) требуют только `_permission: 'access content'`. При необходимости ограничьте доступ через настройки маршрутов.

---

## 18. Релизы

### Процесс выпуска релиза

1. Внести изменения в код модуля
2. Обновить версию в `hotel_reservation.info.yml` (поле `version`)
3. Обновить версию в `composer.json` (поле `extra.drupal.version`) — если используется для Packagist
4. Закоммитить: `git add -A && git commit -m "description of changes (vX.Y.Z)"`
5. Создать тег: `git tag vX.Y.Z`
6. Пушить код и тег: `git push && git push origin vX.Y.Z`
7. **Создать GitHub Release** через API:

```bash
curl -X POST \
  -H "Authorization: Bearer $GITHUB_TOKEN" \
  -H "Accept: application/vnd.github+json" \
  https://api.github.com/repos/Mmitekk/hotel-reservation/releases \
  -d '{
    "tag_name": "vX.Y.Z",
    "name": "vX.Y.Z",
    "body": "- Описание изменений"
  }'
```

Или через GitHub CLI: `gh release create vX.Y.Z --title 'vX.Y.Z' --notes '...'

### Версионирование

Семантическое версионирование (semver):
- **MAJOR** (X.0.0) — обратимо несовместимые изменения
- **MINOR** (1.X.0) — новый функционал, обратимо совместимый
- **PATCH** (1.6.X) — исправления багов

### Список релизов

| Версия | Описание |
|---|---|
| 1.6.4 | Исправлено: HTML-теги в описаниях номеров не отображаются как текст |
| 1.6.3 | Обёртка .page-content-section для заголовков блоков, исправление цвета кнопки превью |
| 1.6.2 | Блок сравнения: полный набор конфигураций |
| 1.6.1 | Исправлено: модальное окно не открывается автоматически; вкладка аналитики |
| 1.6.0 | Блок сравнения номеров, аналитика, фильтры бронирований, экспорт CSV |

---

## 19. Известные ограничения

1. **Нет тестов.** Отсутствуют PHPUnit, Kernel, Functional, JavaScript тесты.
2. **Нет `hook_update_N()`.** При изменении схемы сущностей нужно вручную выполнять `drush entity-updates`.
3. **Нет сервисного слоя.** Вся бизнес-логика в глобальных функциях `.module` и контроллерах.
4. **Нет DI в частых местах.** `DashboardController`, `AnalyticsController`, блоки используют `\Drupal::` вместо внедрения зависимостей.
5. **`description` номера содержит HTML.** Всегда используйте `strip_tags()` перед выводом в текстовом контексте (карточки, таблица сравнения, JSON API возвращает сырой HTML).
6. **API не авторизован.** Все 3 эндпоинта доступны анонимно (только `access content`).
7. **Цвета типов номеров захардкожены.** Массивы `ROOM_TYPE_LABELS` и `ROOM_TYPE_COLORS` в `RoomsBlock.php` нужно обновлять вручную при добавлении типов.
8. **Тема `hotel_reservation_room_card`** ссылается на несуществующий шаблон `room-card.html.twig`. Хук не используется.
9. **`hook_uninstall()` не удаляет таблицы.** Таблицы `hr_room`, `hr_reservation`, `hr_room_pricing` остаются в БД после удаления модуля.
10. **`composer.json extra.drupal.version` может не совпадать с `hotel_reservation.info.yml version`.** Composer использует теги git для версий, не поле из info.yml.
