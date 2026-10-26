---
title: Модульная система MyDoMo — core-модули
audience: user, agent
category: core
tags: модули, core, ModuleManager, ModuleDb, SQLite, события, Yoyo, архитектура
description: Утверждённая модель core-модулей MyDoMo — структура, права, БД, события, layout и порядок портирования.
order: 6
---

# Модульная система MyDoMo — core-модули

Статус: **утверждено 2026-10-26**. Это не черновик и не «фантазии»:
документ задаёт контракт core-модулей. Third-party модули появятся позже
отдельным этапом.

## 1. Два типа модулей

| Тип | Где живёт | Удаление | Отключение | Исполнение |
| --- | --- | --- | --- | --- |
| `core` | `modules/core/<id>/` | нельзя | нельзя | in-process |
| `third-party` | `modules/<id>/` | можно | можно | отдельный этап |

Всё, что сейчас лежит в `modules/`, считается **core-поставкой**. Это
историческое наследие: модули ядра когда-то были обычными модулями
MajorDoMo, но в MyDoMo они не являются устанавливаемыми пакетами.

Core-модуль нельзя удалить или отключить. Если функциональность нужно
остановить, останавливается её цикл/фоновая работа; сам модуль и его код
остаются в системе.

Third-party модули в этой итерации не разрабатываются. Для них заранее
резервируется отдельный каталог и отдельный lifecycle.

## 2. Структура пакета

```text
modules/core/<id>/
├── module.json
├── src/
├── views/
├── routes.php
├── migrations/
├── assets/                 # исходники, если нужны
└── data/                   # только для SQLite/локального состояния
```

`module.json` core-модуля:

```json
{
  "id": "shoutbox",
  "type": "core",
  "name": "Shoutbox",
  "version": "2.0.0",
  "namespace": "MajorDoMo\\Modules\\Core\\Shoutbox\\",
  "src": "src",
  "routes": "routes.php",
  "views": "views",
  "storage": {
    "driver": "mysql",
    "tables": ["messages"]
  },
  "menu": {
    "title": "Shoutbox",
    "icon": "message",
    "route": "shoutbox.index",
    "order": 80
  }
}
```

Обязательные поля: `id`, `type`, `name`, `version`, `namespace`, `routes`,
`views`. `storage` обязателен, если модуль работает с данными.

`src/Modules/` — код менеджера, контрактов и SDK, а не пакеты модулей.

## 3. БД: только своя область

Новые установки не обязаны сохранять старые имена таблиц. MyDoMo
проектируется с нуля; старую базу можно будет перенести отдельным парсером
в новые таблицы.

Правило: физическая таблица модуля — `module_<id>_<logical>`.
Например, `module_shoutbox_messages`, `module_mqtt_topics`.

Модуль получает `ModuleDb`, а не `$db`, `mysqli`, `PDO` или произвольный SQL:

```php
$db = $module->db();

$db->table('messages')->where('MEMBER_ID', $id)->get();
$db->table('messages')->insert(['MESSAGE' => $text]);
```

`ModuleDb`:

- проверяет logical-имя по allowlist из `storage.tables`;
- подставляет префикс `module_<id>_`;
- запрещает `table('users')`, `table('objects')`, чужие префиксы и `..`;
- не даёт `raw()`, `query()`, `exec()`, `selectRaw()`, `whereRaw()` и
  произвольные JOIN;
- DDL/DROP — только через миграции модуля и только для своих таблиц;
- поддерживает два драйвера:
  - `mysql` — общая MariaDB, но только свои таблицы;
  - `sqlite` — отдельный файл `data/module.sqlite` для статических/локальных
    данных.

SQLite — полноценный вариант, а не «временная заглушка». Модуль сам выбирает
драйвер в манифесте.

Прямой SQL в коде модуля и пользовательских сценариях запрещён. `CodeGuard`
ловит `SQLSelect*`, `$db`, `mysqli_*`, `PDO`, SQL-строки и не даёт сохранить
или выполнить такой код. Это ограничение API-уровня, а не OS-изоляция:
злонамеренный PHP всё ещё может пытаться обойти его, поэтому код-ревью и
запрет опасных функций остаются обязательными.

## 4. Обмен публичными данными

Модули не читают таблицы друг друга. Для публичной информации используется
`ModuleBus` — события с TTL и сохранением последнего значения:

```php
$module->bus()->publish('router.42.online', ['online' => true], ttl: 300);

$last = $module->bus()->latest('keenetic.router.42.online');
$module->bus()->subscribe('keenetic.router.*', $callback);
```

- topic namespaced: `module.<id>.*`;
- писать можно только в свой namespace;
- `latest()` возвращает последнее событие, пока не истёк TTL;
- `subscribe()` сейчас доставляет события в текущем процессе; фоновая
  доставка через очередь — следующий шаг;
- хранение сейчас — таблица `module_shared_events` в MySQL/SQLite; Redis-адаптер
  добавим позже, контракт `publish/latest` от него не зависит;
- core-модули доверенные, но контракт topic/namespace единый.

Это закрывает сценарий «Keenetic сообщает, что роутер включён, другие модули
читают», не открывая чужие таблицы.

## 5. Frontend

Core-модуль не приносит собственный layout и не создаёт новый дизайн
админки. Используется существующая alpha-оболочка:

- `templates/panel.html`, `templates/panel_nav_left.html`;
- `resources/css/ui-system.css`, `resources/css/admin_panel.css` и
  `public/assets/admin/admin.css`;
- серверные компоненты `lib/ui/MDJUI.php` и `templates/ui/components/`;
- страница UI Kit `?action=ui_kit` / `templates/ui_kit/` — компоненты
  добавляем и перерабатываем там, а не в отдельном новом layout.

Модуль отдаёт только содержимое страницы (Blade-секцию/Yoyo-компонент),
которое вставляется в alpha-оболочку. Существующие представления alpha
переносим с PHP/HTML на Blade/Yoyo, сохраняя те же `md-*`-классы, разметку
и внешний вид; новый дизайн не рисуем. Yoyo-компоненты модуля регистрируются
в `MajorDoMo\Modules\Core\<Name>\Yoyo\`; CSS/JS — page-only, через Vite или
`public/assets/modules/<id>/`.

Никакого отдельного origin, iframe или PHP-FPM pool для core-модулей нет.
Yoyo — основной способ дать модулю интерактивность внутри существующей
админки.

## 6. Lifecycle

Core:

- discover из `modules/core/*/module.json`;
- миграции выполняются при обновлении ядра;
- `module:list` показывает `type=core`, `removable=false`, `enabled=true`;
- `module:remove`/`module:disable` для core запрещены на уровне менеджера;
- код и данные обновляются только вместе с ядром.

Third-party (позже):

- install/update/disable/remove;
- свои `data/`, vendor, миграции;
- удаление снимает меню, routes, jobs, shared-топики и ассеты.

## 7. Порядок портирования core-модулей

1. `ui_kit` — проверка alpha-компонентов и Yoyo без нового дизайна.
2. `shoutbox` — первая БД-таблица, форма, CSRF в alpha-оболочке.
3. `modes` — настройки и доменная логика.
4. `locations`, `pinghosts`, `actions_log`, `history`, `pvalues`.
5. `system_errors`, `jobs`, `linkedobject`, `knowledge_base`.
6. `api`, `xray`, `market`, `saverestore`.
7. `mqtt`, `zigbee`, `ai_assistant`, `blockly_code`.
8. `classes`, `objects`, `properties`, `methods`, `scripts`, `events`,
   `users`, `settings`, `control_modules`, `panel`.

Порядок можно менять, но `panel`/`objects` идут последними: они зависят
от всех остальных контрактов.

## 8. Текущее состояние

- `ModuleManager` сканирует только `modules/core`;
- `modules/welcome` удалён;
- `ModuleDb`/`ModuleBus` готовы;
- `shoutbox` перенесён полностью: `modules/core/shoutbox`, `ModuleDb`, Blade,
  alpha-layout; старый код удалён;
- `jobs` перенесён на core-очередь: pending/reserved/failed, retry/forget;
  старый scheduler-модуль удалён;
- `actions_log` перенесён на новую `action_log`: фильтр/поиск/soft delete;
- `settings` перенесён на новую lowercase-таблицу `settings`;
- `users` перенесён на новую таблицу `users`: список/создание/редактирование/
  soft delete, пароли через `password_hash`;
- остальные модули — после закрытия оставшегося core-gate.

## 8. Публичные helpers модуля

Модуль может опубликовать функции для пользовательского кода. Это не
доступ к чужим таблицам, а **фасад над сервисом модуля**:

```php
// modules/core/kinetic/helpers.php
function kinetic_change_connection(string $connection): bool
{
    return app()
        ->modules()
        ->service('kinetic', KineticService::class)
        ->changeConnection($connection);
}
```

`module.json`:

```json
"helpers": "helpers.php"
```

или несколько файлов:

```json
"helpers": ["helpers.php", "helpers_extra.php"]
```

`ModuleManager` подключает их автоматически при обнаружении модуля. Если
`helpers` не указан, ищется `helpers.php` в корне модуля.

Правила:

- имя функции начинается с id модуля (`kinetic_...`, `shoutbox_...`), чтобы
  не было коллизий;
- helper — тонкий фасад; данные читает/пишет сервис модуля;
- helper не ходит в чужие таблицы и не содержит SQL;
- сервис обязан принадлежать namespace модуля — `ModuleManager::service()`
  это проверяет;
- helpers подключаются только в новом ядре; alpha-хелперы не затрагиваются.

Пример из MyDoMo: `shoutbox_latest()` → `ShoutboxService::latest()`. Такой
helper можно звать из сценариев/методов, не зная, как устроена таблица.

## 9. Что запрещено

- Удалять/отключать core-модуль через менеджер.
- Обращаться из модуля к чужим таблицам, `$db`, `.env`, `lib/` и файлам ядра.
- Добавлять raw SQL, `eval`, shell и произвольные пути в модульный код.
- Делать core-модуль отдельным origin/контейнером ради «изоляции»: для core
  достаточно API-границ и код-ревью.
- Оставлять старый код модуля после порта: удаляем в том же коммите.

## Связанные документы

- [Мастер-план MyDoMo](../todo/mydomo.md)
- [Правила для агентов](AGENTS.md)
- [Архитектура](architecture.md)
- [Дизайн-система](../agents/DESIGN_SYSTEM.md)
