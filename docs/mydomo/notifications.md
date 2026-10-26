---
title: MyDoMo — центр уведомлений
audience: user, agent
category: core
tags: mydomo, уведомления, колокольчик, drawer, notify, api
description: Как модули и сценарии оставляют уведомления в админке MyDoMo: таблица notifications, сервис, API, колокольчик и правила прочтения.
order: 9
---

# Центр уведомлений

Уведомление — короткое сообщение модуля или сценария, которое ждёт внимания
администратора. Оно не блокирует действие и не запускает фоновые процессы:
запись в БД, колокольчик в шапке и drawer в админке.

## 1. Таблица `notifications`

| Поле | Смысл |
| --- | --- |
| `module` | код модуля (`events`, `mqtt`, `system`) |
| `type` | `info`, `success`, `warning`, `danger` |
| `message` | короткий текст, 1–255 символов |
| `title`, `details` | необязательные заголовок и подробности |
| `url`, `label` | внутренняя ссылка (`/...`) и текст кнопки |
| `notification_key` | ключ дедупликации для повторяющихся событий |
| `read_at` | время явного прочтения; `null` — непрочитанное |

`notifications` создаётся миграцией `2026_10_26_000020_create_notifications.php`.
Непрочитанные строки не удаляются автоматически; прочитанные остаются для
истории. Отдельного retention пока нет.

## 2. Сервис `NotificationService`

```php
use MajorDoMo\Notifications\NotificationService;

$service = app()->container()->make(NotificationService::class);

$result = $service->send('events', 'Цикл остановлен', 'warning', [
    'key' => 'chain:Кухня.temperature',
    'title' => 'Цепочка события',
    'details' => 'Повтор свойства в цепочке',
    'url' => '/events',
    'label' => 'Открыть журнал',
]);
// ['status' => true, 'id' => 12]
```

Правила:

- `module` — строчные латинские буквы, цифры, `_`, `-`; для системных
  сообщений используется `system`;
- сообщение очищается от HTML и обрезается по длине, пустое не принимается;
- `type` нормализуется: `default` → `info`, `error`/`fail` → `danger`,
  `warn` → `warning`, `ok` → `success`;
- `url` — только внутренний абсолютный путь (`/...`), внешние схемы и `//`
  отклоняются;
- повтор с тем же `key` (или тем же текстом и типом) среди непрочитанных
  возвращает `status=false` и `id` существующей записи;
- у модуля не может быть больше 10 непрочитанных уведомлений; лишнее
  отклоняется, пока администратор не прочитает старые.

Выборки:

```php
$service->listing();                 // total, counts, levels, severity, items, offset
$service->listing('events', 50);     // только модуль, вторая страница
$service->summary();                 // total, counts, levels, severity
$service->dismiss(12);               // прочитать одно
$service->dismiss(null, 'events');   // прочитать все непрочитанные модуля
```

`items` — до 50 записей, сначала `danger`/`warning`, затем свежие. Поле
`module_url` ведёт в ядровой раздел (`CoreSections`) или `/module/<id>` для
модуля; для `system` — `/admin`.

## 3. Обёртка `notify()`

Для сценариев и методов есть простая системная обёртка:

```php
notify('Датчик на кухне не отвечает', 'warning', [
    'key' => 'sensor:kitchen',
    'url' => '/errors',
]);
```

`notify()` пишет от модуля `system`, возвращает результат
`NotificationService::send()`: `status`, `id` или `error`.

## 4. API админки

```text
GET  /api/v1/panel/notifications?module=<id>&offset=<n>
POST /api/v1/panel/notifications/dismiss     {"id": 12}
POST /api/v1/panel/notifications/dismiss     {"module": "events"}
```

GET требует сессию администратора, POST — ещё и заголовок `X-CSRF-Token`
(страница передаёт его из `meta[name="csrf-token"]` через `MDJAdminApi`).
Ответ — конверт `{ok, data}`; у `dismiss` в `data` лежит `dismissed`.

## 5. Колокольчик и drawer

В `layouts.alpha` есть:

- кнопка `[data-md-notifications-open]` с бейджем
  `[data-md-notifications-count]` в шапке;
- общий drawer `#mdAdminDrawerHost` (`20_drawer.js`);
- скрытый блок `#mdNotificationsContent` (`61_module_notifications.js`),
  который переносится в drawer при открытии.

Открытие списка не считается прочтением: запись скрывается только после
кнопки «Прочитано» (или явного `dismiss`). JS обновляет счётчик при загрузке,
при возврате на вкладку и раз в минуту; WebSocket-толка для уведомлений пока
нет.

## 6. Чего пока нет

- push по WebSocket и отдельного события в журнале системы;
- страницы истории прочитанных уведомлений и retention;
- per-module блоков в карточках модулей (API уже отдаёт `counts`/`levels`);
- ограничения прав: уведомления видит любой вошедший в админку.

## Связанные документы

- [Архитектура](architecture.md)
- [Функции и обёртки](functions.md)
- [Модульная система](modules.md)
- [Мастер-план](../todo/mydomo.md)
