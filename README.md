<details>
<summary><b>🇷🇺 Русский</b></summary>

# 🏨 Hotel Reservation — Модуль бронирования номеров отеля для Drupal 9/10

Полнофункциональный модуль бронирования номеров отеля с админ-панелью, ценообразованием по датам, календарём занятости и современной AJAX-формой бронирования на сайте.

## ✨ Возможности

| Возможность | Описание |
|---|---|
| **Управление номерами** | Создание, редактирование, удаление номеров (название, описание, вместимость, базовая цена, удобства) |
| **Индивидуальные цены по датам** | Установка кастомных цен на конкретные даты (праздники, выходные, высокие сезоны) |
| **Календарь занятости** | Визуальный календарь всех номеров с отображением статусов бронирований |
| **Управление бронированиями** | Просмотр, редактирование, подтверждение, отмена бронирований с цветными статусами |
| **Современная форма бронирования** | AJAX-форма в 3 шага (Даты → Номер → Данные), заменяет Webform |
| **Настройки** | Условия бронирования, мин/макс срок проживания, время заезда/выезда, уведомления |
| **Уведомления** | Автоматическая отправка писем гостю и администратору |
| **Авто-истечение** | Автоматическая отмена неподтверждённых бронирований по крону |
| **API-эндпоинты** | JSON API для проверки доступности и отправки бронирований |

## 📸 Скриншоты

### Форма бронирования на сайте (блок Booking Form)
Форма состоит из 3 шагов:
1. **Выбор дат** — заезд, выезд, количество гостей
2. **Выбор номера** — карточки доступных номеров с ценами
3. **Заполнение данных** — ФИО, телефон, email, заметки, подтверждение

### Админка
- `/admin/hotel-reservation/rooms` — Управление номерами
- `/admin/hotel-reservation/reservations` — Список бронирований с фильтрами
- `/admin/hotel-reservation/calendar` — Календарь занятости всех номеров
- `/admin/hotel-reservation/rooms/{id}/pricing` — Установка цен по датам
- `/admin/config/hotel-reservation/settings` — Настройки модуля

### Статусы бронирований
| Статус | Обозначение | Описание |
|---|---|---|
| Pending (Ожидает) | P 🟡 | Новое бронирование, ожидает подтверждения |
| Confirmed (Подтверждено) | C 🟢 | Подтверждено администратором |
| Checked in (Заселён) | I 🔵 | Гость заселён |
| Checked out (Выехал) | O ⚪ | Гость выехал |
| Cancelled (Отменено) | X 🔴 | Отменено |
| Expired (Истекло) | X 🔴 | Автоматически отменено по истечении срока |

## 📦 Установка через Composer

### Требования
- Drupal 9.5+ или Drupal 10.x
- PHP 7.4+
- Модули: datetime, options, field, user, system (входят в ядро Drupal)

### Шаг 1: Добавьте репозиторий в `composer.json`

Отредактируйте файл `composer.json` вашего проекта Drupal (находится в корне сайта):

```bash
cd /path/to/your/drupal/site
```

Добавьте репозиторий:

```bash
composer config repositories.hotel-reservation vcs https://github.com/Mmitekk/hotel-reservation.git
```

### Шаг 2: Установите модуль

```bash
composer require mmitekk/hotel-reservation:^1.0.0
```

### Шаг 3: Включите модуль

```bash
drush en hotel_reservation -y
```

Или через интерфейс: **Управление → Расширения** → включите **Hotel Reservation**.

### Шаг 4: Примените обновления БД

После включения модуль автоматически создаст необходимые таблицы в базе данных.

```bash
drush updatedb -y
```

### Шаг 5: Настройте права доступа

1. Перейдите в **Управление → Люди → Роли** (`/admin/people/roles`)
2. Для роли **Администратор** включите разрешение **Administer hotel reservation**
3. Для роли **Анонимный пользователь** (или соответствующей) включите:
   - **View hotel reservation**
   - **Create hotel reservation**

### Шаг 6: Настройте модуль

1. Перейдите в **Configuration → Hotel Reservation → Settings** (`/admin/config/hotel-reservation/settings`)
2. Заполните:
   - **Название отеля** — будет отображаться в письмах и уведомлениях
   - **Символ валюты** — по умолчанию `₽`
   - **Код валюты** — по умолчанию `RUB`
   - **Минимальный срок проживания** — по умолчанию 1 ночь
   - **Максимальный срок проживания** — по умолчанию 30 ночей
   - **Время заезда / выезда** — по умолчанию 14:00 / 12:00
   - **Условия бронирования** — текст, отображаемый на форме
   - **Email для уведомлений** — куда отправлять уведомления о новых бронированиях

### Шаг 7: Добавьте номера

1. Перейдите в **Configuration → Hotel Reservation → Rooms** (`/admin/hotel-reservation/rooms`)
2. Нажмите **Add Room**
3. Заполните данные номера:
   - **Название** — например, «Двухместный стандарт»
   - **Описание** — подробное описание
   - **Вместимость** — количество гостей (от 1)
   - **Базовая цена за ночь** — цена по умолчанию
   - **Удобства** — через запятую: `Wi-Fi, ТВ, Кондиционер, Минибар`
   - **Опубликован** — включите, чтобы номер отображался на сайте

### Шаг 8: Установите кастомные цены (опционально)

1. В списке номеров нажмите **Pricing** рядом с нужным номером
2. В календаре цен измените цену на нужную дату
3. Нажмите **Сохранить**

Цены, отличные от базовой, подсвечиваются жёлтым и показывают разницу.

### Шаг 9: Разместите форму бронирования

1. Перейдите в **Structure → Block layout** (`/admin/structure/block`)
2. Найдите блок **Hotel Booking Form**
3. Нажмите **Place Block** и выберите регион
4. Настройте:
   - **Form Title** — заголовок формы (если пуст — используется название отеля)
   - **Show booking conditions** — показывать ли условия бронирования

> 💡 **Совет:** Чтобы заменить старую Webform `.front-form-block`, просто:
> 1. Поместите новый блок **Hotel Booking Form** в тот же регион
> 2. Отключите или удалите старый Webform-блок

## 🔧 Использование

### Процесс бронирования гостем
1. Гость выбирает даты заезда/выезда и количество гостей
2. Нажимает «Поиск доступных номеров»
3. Выбирает номер из списка доступных
4. Заполняет ФИО, телефон, email
5. Нажимает «Забронировать»
6. Бронирование создаётся со статусом **Pending**
7. Гость получает письмо-подтверждение (если включено)
8. Администратор получает уведомление (если включено)

### Управление бронированиями
1. Перейдите в **Hotel Reservation → Reservations**
2. Используйте фильтры (статус, дата, номер)
3. Для изменения статуса нажмите:
   - **Confirm** — подтвердить бронирование
   - **Check In** — отметить заселение
   - **Check Out** — отметить выезд
   - **Cancel** — отменить бронирование

### Автоматическая отмена
Модуль автоматически отменяет (статус **Expired**) неподтверждённые бронирования по истечении заданного времени (по умолчанию 24 часа). Для работы этого механизма cron должен быть настроен.

```bash
drush cron
```

## 📂 Структура модуля

```
hotel_reservation/
├── hotel_reservation.info.yml          # Метаданные модуля
├── hotel_reservation.module            # Основные хуки
├── hotel_reservation.routing.yml       # Маршруты
├── hotel_reservation.permissions.yml   # Права доступа
├── hotel_reservation.links.menu.yml    # Ссылки в админ-меню
├── hotel_reservation.links.action.yml  # Кнопки действий
├── hotel_reservation.links.task.yml    # Локальные задачи (табы)
├── hotel_reservation.libraries.yml     # CSS/JS библиотеки
├── composer.json                       # Composer-пакет
├── config/schema/                      # Схема конфигурации
│   └── hotel_reservation.schema.yml
├── css/
│   ├── booking-form.css                # Стили формы бронирования
│   ├── admin-calendar.css              # Стили календаря
│   └── room-pricing.css                # Стили цен
├── js/
│   └── booking-form.js                 # AJAX-логика формы
├── src/
│   ├── Entity/
│   │   ├── Room.php                    # Сущность «Номер»
│   │   ├── Reservation.php             # Сущность «Бронирование»
│   │   └── RoomPricing.php             # Сущность «Цена по дате»
│   ├── Form/
│   │   ├── RoomForm.php                # Форма номера
│   │   ├── ReservationForm.php         # Форма бронирования (админка)
│   │   └── SettingsForm.php            # Форма настроек
│   ├── Controller/
│   │   ├── HotelReservationController.php  # Админ-контроллер
│   │   └── ApiController.php           # API-контроллер
│   ├── Plugin/Block/
│   │   └── BookingFormBlock.php        # Блок формы бронирования
│   ├── RoomListBuilder.php             # Список номеров
│   └── ReservationListBuilder.php      # Список бронирований
└── templates/
    └── admin-calendar.html.twig        # Шаблон календаря
```

## 🔄 Обновление

```bash
composer update mmitekk/hotel-reservation
drush updatedb -y
drupal cr all
```

## ❓ Часто задаваемые вопросы

**Q: Как изменить внешний вид формы бронирования?**
A: Редактируйте файл `css/booking-form.css`. Форма использует классы с префиксом `hr-`.

**Q: Можно ли интегрировать с платёжными системами?**
A: Модуль создаёт бронирование со статусом «Pending». Вы можете написать дополнительный код (hook) или отдельный подмодуль для интеграции с платёжной системой (ЮKassa, SberPay, Tinkoff Pay и т.д.).

**Q: Работает ли модуль с мультиязычностью?**
A: Да, все строки модуля используют систему перевода Drupal (t()). Переводы можно добавить через стандартный интерфейс (`/admin/config/regional/translate`).

**Q: Как экспортировать/импортировать данные бронирований?**
A: Используйте Views (если установлен) или создавайте Views программно для экспорта в CSV/Excel.

## 📄 Лицензия

GPL-2.0-or-later

</details>

---

<details>
<summary><b>🇬🇧 English</b></summary>

# 🏨 Hotel Reservation — Room Booking Module for Drupal 9/10

A full-featured hotel room booking module with an admin panel, per-date pricing, occupancy calendar, and modern AJAX booking form.

## ✨ Features

| Feature | Description |
|---|---|
| **Room Management** | Create, edit, delete rooms (name, description, capacity, base price, amenities) |
| **Per-Date Pricing** | Set custom prices for specific dates (holidays, weekends, peak seasons) |
| **Occupancy Calendar** | Visual calendar of all rooms with reservation status indicators |
| **Reservation Management** | View, edit, confirm, cancel reservations with color-coded statuses |
| **Modern Booking Form** | 3-step AJAX form (Dates → Room → Details), replaces standard Webform |
| **Configurable Settings** | Booking conditions, min/max stay, check-in/out times, notifications |
| **Email Notifications** | Automatic emails to guests and administrators |
| **Auto-Expiration** | Cron-based automatic cancellation of unconfirmed reservations |
| **API Endpoints** | JSON API for availability checking and reservation submission |

## 📸 Overview

### Booking Form on the Website (Booking Form Block)
The form consists of 3 steps:
1. **Select Dates** — check-in, check-out, guest count
2. **Choose Room** — cards of available rooms with prices
3. **Enter Details** — full name, phone, email, notes, confirmation

### Admin Panel
- `/admin/hotel-reservation/rooms` — Room management
- `/admin/hotel-reservation/reservations` — Reservation list with filters
- `/admin/hotel-reservation/calendar` — Occupancy calendar for all rooms
- `/admin/hotel-reservation/rooms/{id}/pricing` — Per-date pricing calendar
- `/admin/config/hotel-reservation/settings` — Module settings

### Reservation Statuses
| Status | Code | Description |
|---|---|---|
| Pending | P 🟡 | New reservation, awaiting confirmation |
| Confirmed | C 🟢 | Confirmed by administrator |
| Checked In | I 🔵 | Guest has checked in |
| Checked Out | O ⚪ | Guest has checked out |
| Cancelled | X 🔴 | Cancelled |
| Expired | X 🔴 | Automatically cancelled after timeout |

## 📦 Installation via Composer

### Requirements
- Drupal 9.5+ or Drupal 10.x
- PHP 7.4+
- Modules: datetime, options, field, user, system (included in Drupal core)

### Step 1: Add the Repository to `composer.json`

Navigate to your Drupal project root:

```bash
cd /path/to/your/drupal/site
```

Add the repository:

```bash
composer config repositories.hotel-reservation vcs https://github.com/Mmitekk/hotel-reservation.git
```

### Step 2: Install the Module

```bash
composer require mmitekk/hotel-reservation:^1.0.0
```

### Step 3: Enable the Module

```bash
drush en hotel_reservation -y
```

Or via UI: **Manage → Extend** → enable **Hotel Reservation**.

### Step 4: Apply Database Updates

After enabling, the module will automatically create the necessary database tables.

```bash
drush updatedb -y
```

### Step 5: Configure Permissions

1. Go to **People → Roles** (`/admin/people/roles`)
2. For the **Administrator** role, enable **Administer hotel reservation**
3. For **Anonymous user** (or the appropriate role), enable:
   - **View hotel reservation**
   - **Create hotel reservation**

### Step 6: Configure the Module

1. Go to **Configuration → Hotel Reservation → Settings** (`/admin/config/hotel-reservation/settings`)
2. Fill in:
   - **Hotel Name** — displayed in emails and notifications
   - **Currency Symbol** — default `₽`
   - **Currency Code** — default `RUB`
   - **Minimum Stay** — default 1 night
   - **Maximum Stay** — default 30 nights
   - **Check-in / Check-out Time** — default 14:00 / 12:00
   - **Booking Conditions** — text displayed on the form
   - **Notification Email** — where to send new reservation notifications

### Step 7: Add Rooms

1. Go to **Configuration → Hotel Reservation → Rooms** (`/admin/hotel-reservation/rooms`)
2. Click **Add Room**
3. Fill in the room details:
   - **Name** — e.g., "Double Standard"
   - **Description** — detailed description
   - **Capacity** — number of guests (from 1)
   - **Base Price per Night** — default price
   - **Amenities** — comma-separated: `Wi-Fi, TV, AC, Minibar`
   - **Published** — enable to show on the website

### Step 8: Set Custom Prices (Optional)

1. In the room list, click **Pricing** next to the desired room
2. In the pricing calendar, change the price for any date
3. Click **Save**

Prices different from the base price are highlighted in yellow with a diff indicator.

### Step 9: Place the Booking Form

1. Go to **Structure → Block layout** (`/admin/structure/block`)
2. Find the **Hotel Booking Form** block
3. Click **Place Block** and select the desired region
4. Configure:
   - **Form Title** — custom form title (if empty, the hotel name from settings is used)
   - **Show booking conditions** — whether to display booking conditions

> 💡 **Tip:** To replace the old `.front-form-block` Webform:
> 1. Place the new **Hotel Booking Form** block in the same region
> 2. Disable or remove the old Webform block

## 🔧 Usage

### Guest Booking Process
1. Guest selects check-in/check-out dates and number of guests
2. Clicks "Search Available Rooms"
3. Selects a room from the list of available rooms
4. Fills in name, phone, email
5. Clicks "Book Now"
6. Reservation is created with **Pending** status
7. Guest receives a confirmation email (if enabled)
8. Administrator receives a notification (if enabled)

### Managing Reservations
1. Go to **Hotel Reservation → Reservations**
2. Use filters (status, date, room)
3. To change status, click:
   - **Confirm** — confirm the reservation
   - **Check In** — mark guest check-in
   - **Check Out** — mark guest check-out
   - **Cancel** — cancel the reservation

### Automatic Expiration
The module automatically cancels (status **Expired**) unconfirmed reservations after a configurable timeout (default 24 hours). Cron must be configured for this to work.

```bash
drush cron
```

## 📂 Module Structure

```
hotel_reservation/
├── hotel_reservation.info.yml          # Module metadata
├── hotel_reservation.module            # Core hooks
├── hotel_reservation.routing.yml       # Routes
├── hotel_reservation.permissions.yml   # Permissions
├── hotel_reservation.links.menu.yml    # Admin menu links
├── hotel_reservation.links.action.yml  # Action links
├── hotel_reservation.links.task.yml    # Local tasks (tabs)
├── hotel_reservation.libraries.yml     # CSS/JS libraries
├── composer.json                       # Composer package
├── config/schema/                      # Config schema
│   └── hotel_reservation.schema.yml
├── css/
│   ├── booking-form.css                # Booking form styles
│   ├── admin-calendar.css              # Calendar styles
│   └── room-pricing.css                # Pricing styles
├── js/
│   └── booking-form.js                 # Form AJAX logic
├── src/
│   ├── Entity/
│   │   ├── Room.php                    # Room entity
│   │   ├── Reservation.php             # Reservation entity
│   │   └── RoomPricing.php             # Per-date pricing entity
│   ├── Form/
│   │   ├── RoomForm.php                # Room form
│   │   ├── ReservationForm.php         # Reservation form (admin)
│   │   └── SettingsForm.php            # Settings form
│   ├── Controller/
│   │   ├── HotelReservationController.php  # Admin controller
│   │   └── ApiController.php           # API controller
│   ├── Plugin/Block/
│   │   └── BookingFormBlock.php        # Booking form block
│   ├── RoomListBuilder.php             # Room list builder
│   └── ReservationListBuilder.php      # Reservation list builder
└── templates/
    └── admin-calendar.html.twig        # Calendar template
```

## 🔄 Updating

```bash
composer update mmitekk/hotel-reservation
drush updatedb -y
drupal cr all
```

## ❓ FAQ

**Q: How do I customize the booking form appearance?**
A: Edit `css/booking-form.css`. The form uses `hr-` prefixed CSS classes.

**Q: Can I integrate with payment systems?**
A: The module creates reservations with "Pending" status. You can write custom code (hooks) or a sub-module for payment integration (YooKassa, Stripe, etc.).

**Q: Does the module support multilingual sites?**
A: Yes, all module strings use Drupal's translation system (t()). Translations can be added via the standard interface (`/admin/config/regional/translate`).

**Q: How to export/import reservation data?**
A: Use the Views module (if installed) or create programmatic Views for CSV/Excel export.

## 📄 License

GPL-2.0-or-later

</details>