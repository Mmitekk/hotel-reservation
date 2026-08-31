<details>
<summary><b>🇷🇺 Русский</b></summary>

# 🏨 Hotel Reservation — Модуль бронирования номеров отеля для Drupal 9/10

Полнофункциональный модуль бронирования номеров отеля с админ-панелью, ценообразованием по датам, календарём занятости и современной AJAX-формой бронирования на сайте.

## ✨ Возможности

| Возможность | Описание |
|---|---|
| **Управление номерами** | Создание, редактирование, удаление номеров (название, описание, тип, вместимость, базовая цена, удобства) |
| **Индивидуальные цены по датам** | Установка кастомных цен на конкретные даты (праздники, выходные, высокие сезоны) |
| **Календарь занятости** | Визуальный календарь всех номеров с отображением статусов бронирований |
| **Управление бронированиями** | Просмотр, редактирование, подтверждение, отмена бронирований с цветными статусами |
| **Современная форма бронирования** | AJAX-форма в 3 шага (Даты → Номер → Данные) с выбором даты и времени |
| **Настройки** | Условия бронирования, мин/макс срок проживания (до 9999 ночей), время заезда/выезда, уведомления |
| **Настройка дизайна формы** | Цвета кнопок/фона/текста, радиус скругления, кастомные заголовки прямо из админки |
| **Маска телефона +7** | Автоматическое форматирование номера телефона в формате +7 (XXX) XXX-XX-XX |
| **Уведомления** | Автоматическая отправка писем гостю и администратору |
| **Авто-истечение** | Автоматическая отмена неподтверждённых бронирований по крону |
| **Экспорт CSV** | Экспорт бронирований с фильтрами в CSV (BOM для Excel UTF-8) |
| **Русский перевод** | Встроенный русский перевод всего интерфейса |
| **API-эндпоинты** | JSON API для проверки доступности и отправки бронирований |

## 📦 Установка через Composer

### Требования
- Drupal 9.5+ или Drupal 10.x
- PHP 7.4+
- Модули: datetime, options, field, user, system (входят в ядро Drupal)

### Быстрая установка

```bash
cd /path/to/your/drupal/site

# Удалить старую копию модуля (если была установлена вручную)
rm -rf modules/custom/hotel_reservation
rm -rf modules/contrib/hotel-reservation
rm -rf vendor/mmitekk

# Очистить кэш Composer
composer clear-cache

# Установить модуль (с --ignore-platform-reqs если есть конфликт drupal/upgrade_status)
composer require mmitekk/hotel-reservation:^1.4.0 --no-interaction --ignore-platform-reqs

# Включить модуль
 drush en hotel_reservation -y
 drush updatedb -y
 drush cr
```

### Установка вопреки update_status

Если Composer жалуется на несовместимость с `drupal/upgrade_status`, используйте:

```bash
composer82 require mmitekk/hotel-reservation --no-interaction --ignore-platform-reqs
```

### Импорт русского перевода

После включения модуля:
1. Перейдите в **Конфигурация → Региональные настройки и язык → Перевод интерфейса** (`/admin/config/regional/translate`)
2. Нажмите **Импортировать**
3. Выберите файл `translations/ru.po` из директории модуля
4. Язык: Russian
5. Нажмите **Импортировать**

Или импортируйте через drush (если установлен locale module):

```bash
drush locale:import ru --type=po modules/contrib/hotel-reservation/translations/ru.po
```

## ⚙️ Настройки

Перейдите в **Конфигурация → Hotel Reservation → Settings** (`/admin/config/hotel-reservation/settings`)

### Информация об отеле
- **Название отеля** — отображается в письмах и заголовке формы
- **Символ валюты** — по умолчанию `₽`
- **Код валюты** — по умолчанию `RUB`

### Правила бронирования
- **Минимальный срок** (1–9999 ночей)
- **Максимальный срок** (1–9999 ночей)
- **Время заезда / выезда** — по умолчанию 14:00 / 12:00
- **Условия бронирования** — текст на форме

### Дизайн формы бронирования
- **Заголовок формы** — если пуст, используется название отеля
- **Подзаголовок** — если пуст, показывается «Заезд с {время}, выезд до {время}»
- **Текст кнопки** — текст кнопки «Забронировать»
- **Основной цвет** — цвет кнопок и акцентов (color picker)
- **Цвет фона** — цвет фона формы (color picker)
- **Цвет текста** — основной цвет текста (color picker)
- **Скругление (px)** — радиус скругления элементов формы
- **Заголовок успеха** — заголовок после отправки формы
- **Текст успеха** — используйте `@id` для подстановки номера бронирования

### Уведомления
- **Уведомления админу** — письмо при новом бронировании
- **Email админа** — адрес получателя
- **Подтверждение гостю** — письмо гостю при подтверждении

### Автоматизация
- **Авто-отмена через (часов)** — время жизни неподтверждённых бронирований

## 📂 Админ-панель

| Страница | Путь | Описание |
|---|---|---|
| Dashboard | `/admin/hotel-reservation/dashboard` | Статистика, графики выручки, последние бронирования |
| Номера | `/admin/hotel-reservation/rooms` | CRUD номеров с типами и удобствами |
| Бронирования | `/admin/hotel-reservation/reservations` | Список с фильтрами, быстрая смена статуса |
| Аналитика | `/admin/hotel-reservation/analytics` | KPI, графики трендов, распределение статусов |
| Календарь | `/admin/hotel-reservation/calendar` | Календарь занятости по всем номерам |
| Цены | `/admin/hotel-reservation/rooms/{id}/pricing` | Индивидуальные цены по датам |
| Настройки | `/admin/config/hotel-reservation/settings` | Все настройки модуля |

## 🔄 Версии

### v1.6.1
- 🐛 Исправлено: модальное окно формы открывалось автоматически при загрузке страницы
- 📊 Добавлена вкладка «Аналитика» в навигацию админ-панели
- 🔲 Добавлена галочка «Обёртка .page-content-section» в настройках всех блоков
- 📄 Добавлена команда установки вопреки update_status в README

### v1.6.0
- ✨ Блок «Наши номера» — вывод номеров сеткой или каруселью
- ✨ Блок «Сравнение номеров» — интерактивное сравнение 2–3 номеров
- ✨ Страница аналитики — KPI, тренды, распределение статусов, топ номеров
- ✨ Форма бронирования: режим модального окна с превью-карточкой
- 🎨 Обновлённый дизайн всех компонентов

### v1.4.0
- 🔧 Исправлен CSRF-токен при отправке формы бронирования
- 🎨 Настройка дизайна формы из админки (цвета, текст, скругление)
- 📅 Выбор даты и времени заезда/выезда (datetime-local)
- 📞 Маска телефона +7 (XXX) XXX-XX-XX
- 🇷🇺 Встроенный русский перевод
- 📏 Увеличен лимит максимального срока до 9999 ночей
- 🗑️ Убрано дублирование маршрутов (исправление RouteNotFoundException)

### v1.3.0
- 🗑️ Удалены дублирующие маршруты из routing.yml (корень ошибки RouteNotFoundException)
- ✏️ Исправлены имена маршрутов в links (add-form → add_form)

### v1.2.0
- ✨ Добавлен тип номера (Standard, Superior, Deluxe, Suite, Apartment, Villa, Family, Economy)
- 📊 График выручки за неделю на дашборде
- 📥 Кнопка экспорта CSV
- 🎨 Обновлён дизайн админки

---

<details>
<summary><b>🇬🇧 English</b></summary>

# 🏨 Hotel Reservation — Room Booking Module for Drupal 9/10

Full-featured hotel room booking module with admin panel, per-date pricing, occupancy calendar, and modern AJAX booking form.

## ✨ Features

| Feature | Description |
|---|---|
| **Room Management** | CRUD rooms (name, description, type, capacity, base price, amenities) |
| **Per-Date Pricing** | Custom prices for specific dates (holidays, weekends, peak seasons) |
| **Occupancy Calendar** | Visual calendar with reservation status indicators |
| **Reservation Management** | View, edit, confirm, cancel with color-coded statuses |
| **Modern Booking Form** | 3-step AJAX form with date+time picker and phone mask |
| **Configurable Design** | Colors, text, border radius — all from admin settings |
| **Phone Mask +7** | Auto-formatting to +7 (XXX) XXX-XX-XX |
| **Email Notifications** | Automatic emails to guests and administrators |
| **Auto-Expiration** | Cron-based auto-cancellation of pending reservations |
| **CSV Export** | Filtered CSV export with BOM for Excel UTF-8 |
| **Russian Translation** | Built-in Russian translation |
| **API Endpoints** | JSON API for availability and reservation submission |

## 📦 Quick Install

```bash
cd /path/to/your/drupal/site
rm -rf modules/custom/hotel_reservation modules/contrib/hotel-reservation vendor/mmitekk
composer clear-cache
composer require mmitekk/hotel-reservation:^1.4.0 --no-interaction --ignore-platform-reqs
drush en hotel_reservation -y && drush updatedb -y && drush cr
```

### Install bypassing update_status

If Composer complains about `drupal/upgrade_status` incompatibility:

```bash
composer82 require mmitekk/hotel-reservation --no-interaction --ignore-platform-reqs
```

## 📄 License

GPL-2.0-or-later

</details>
