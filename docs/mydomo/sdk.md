---
title: MyDoMo — SDK модулей
audience: agent, user
category: core
tags: mydomo, sdk, модули, module.json, lifecycle, миграции, module_db
description: Контракт стороннего модуля MyDoMo: module.json, entrypoint, ModuleDb, миграции, install/enable/disable/remove и CLI.
order: 12
---

# SDK модулей

Модуль — каталог `modules/<id>/` с `module.json`. Core-модули лежат в
`modules/core/<id>/` и всегда включены; сторонние устанавливаются через
`module:install` и живут по тем же правилам. Legacy `project_modules`,
uppercase-поля и `modules/<id>/<id>.class.php` не используются.

Готовый пример — [`modules/example_sdk`](../../modules/example_sdk/).

Ядровые страницы объектов, пользователей, журналов и API не являются
SDK-модулями: они находятся в `src`/`resources/views/pages` и перечислены
в [core-sections.md](core-sections.md). Каталог `modules/core` — для
встроенных интеграций типа MQTT/Zigbee после их переноса.

## 1. Структура

```text
modules/weather/
├── module.json
├── routes.php
├── src/
│   ├── Module.php          # optional entrypoint
│   └── WeatherController.php
├── views/
│   └── index.blade.php
├── migrations/
│   └── 0001_create_forecasts.php
└── helpers.php             # optional
```

`routes.php` возвращает `callable(Router): void`; маршруты получают префикс
`/module/<id>`, auth, request-id и ModuleDb/ModuleBus этого модуля.

## 2. module.json

```json
{
  "id": "weather",
  "type": "module",
  "name": "Погода",
  "version": "1.2.0",
  "description": "Прогноз для объектов",
  "namespace": "Vendor\\Weather\\",
  "entrypoint": "Module",
  "src": "src",
  "routes": "routes.php",
  "views": "views",
  "migrations": "migrations",
  "helpers": "helpers.php",
  "assets": "assets",
  "storage": {
    "driver": "mysql",
    "tables": ["forecasts"]
  },
  "menu": {
    "title": "Погода",
    "order": 120,
    "icon": "ti-cloud"
  },
  "requires": {
    "mydomo": ">=2.0.0-dev",
    "php": ">=8.2"
  },
  "dependencies": [],
  "permissions": ["weather.view", "weather.manage"]
}
```

Обязательны `id` и `namespace`; `version` при отсутствии нормализуется
в `0.0.0`, но для выпуска пакета её следует задать явно. `type` совпадает с
каталогом: `core` для `modules/core/<id>`, `module` для `modules/<id>`.
`requires` проверяется через semver; `dependencies` — id уже включённых
модулей. Не указывайте ещё не перенесённый MQTT как обязательную зависимость
учебного примера: установщик потребует его включения. Пути относительные,
без `..`. ID — 2–64 символа `[a-z][a-z0-9_]`; дефис в ID не разрешён.

## 3. Entrypoint

Необязательный `src/Module.php` — класс из `namespace` + `entrypoint`,
наследник `MajorDoMo\Modules\Module`:

```php
final class Module extends \MajorDoMo\Modules\Module
{
    public function boot(\MajorDoMo\Modules\ModuleContext $context): void
    {
        // регистрация сервисов, подписки ModuleBus
    }

    public function install(\MajorDoMo\Modules\ModuleContext $context): void {}
    public function uninstall(\MajorDoMo\Modules\ModuleContext $context): void {}
}
```

`ModuleContext` даёт `db()`, `bus()`, `container()`, `root()`, `id()`,
`version()`. Прямой доступ к PDO/`$db` ядра запрещён.

`db()` возвращает null, если storage не объявлен. `root()` сейчас возвращает
корень приложения, а не каталог модуля. Контейнер и root делают модуль
доверенным PHP-кодом; этот контекст не является sandbox.

Фоновые задачи:

```json
{
  "tasks": {
    "poll": {
      "method": "poll",
      "interval": 60,
      "timeout": 30
    }
  }
}
```

`interval` — секунды (1..86400), `timeout` — секунды (1..3600), `enabled`
по умолчанию `true`. Worker запускает один процесс на модуль:
`php bin/mydomo worker <id>`. Метод задачи получает
`MajorDoMo\Worker\BackgroundTaskContext` и делает один проход; циклы,
паузы и рестарты — на стороне ядра. Подробно — [runtime.md](runtime.md).

## 4. Данные

`storage.tables` — логические имена. Физические таблицы получают префикс
`module_<id>_`, например `storage.tables: ["forecasts"]` →
`module_weather_forecasts`. Работать с ними можно только через
`ModuleDb::table('forecasts')`; чужие таблицы запрещены.

Миграции — `migrations/*.php`, файл возвращает `callable(Connection)`.
Они выполняются один раз при `module:install`; факт фиксируется в
`module_migrations`. Имена таблиц в миграции должны совпадать с
`ModuleDb::physicalName()` (или быть идемпотентными).

Сейчас ModuleManager передаёт такой миграции **основное соединение ядра**.
Это не автоматический выбор отдельного SQLite-файла storage-модуля.
Если таблица должна жить именно в этом файле, создавайте её через
context.db() в install-hook либо предварительно дорабатывайте контракт
миграций. Не обещайте, что переключение storage.driver само переносит
все DDL в другое соединение. Незавершённый файл не откатывается автоматически.

## 5. Lifecycle

```bash
php bin/mydomo db:migrate          # создаёт module_registry/module_migrations
php bin/mydomo module:validate      # проверить все module.json
php bin/mydomo module:list          # core + сторонние, installed/enabled
php bin/mydomo module:install weather
php bin/mydomo module:disable weather
php bin/mydomo module:enable weather
php bin/mydomo module:remove weather
```

`module:install` проверяет semver, зависимости, выполняет миграции, вызывает
`install`/`enabled` и включает модуль. `module:remove` вызывает `uninstall`
и убирает запись из реестра; файлы и таблицы не удаляются — это отдельное
решение владельца. Core-модули нельзя disable/remove.

disable вызывает hook disabled и затем записывает enabled=0;
enable повторно проверяет совместимость/зависимости и вызывает enabled.
Установка не является транзакцией вокруг произвольных DDL/hooks: при сбое
может остаться часть таблиц и применённых миграций. Изменение реестра
вступает в силу для маршрутов/меню при следующей сборке приложения;
долгоживущий worker нужно синхронизировать или перезапустить отдельно.

Состояние хранится в `module_registry`; при старте загружаются только
`enabled` сторонние модули. Выключенный модуль не регистрирует маршруты,
меню и helpers.

## 6. Безопасность и границы

- нет `eval`, `$db`, `SQLSelect*`, `mysqli_*`, `PDO` и глобальных путей ядра;
- только `ModuleDb`/`ModuleBus` и сервисы, полученные через контейнер;
- пользовательский ввод — через `Request`, CSRF на изменяющих маршрутах;
- `permissions` — декларация для будущего UI прав, сейчас не автоприменяется;
- поле assets не включает автоматическую публикацию/сборку. Подключение
  локальных ресурсов нужно согласовать с бандлом; новые библиотеки идут
  через npm, без CDN и отдельных script/link на библиотеку в шаблоне.

Пункт «нет PDO/eval» — правило поддерживаемого кода, а не техническая
изоляция от произвольного PHP. Автоматического CodeGuard пока нет.

## 7. Ограничения текущего этапа

- установка только из локального `modules/<id>/`, marketplace позже;
- `module:remove` не удаляет таблицы и файлы;
- миграции не откатываются;
- `permissions` пока не связаны с `roles`/`permissions` автоматически;
- установка во время HTTP-запроса не поддерживается — только CLI.

## Связанные документы

- [Модульная система](modules.md)
- [Архитектура](architecture.md)
- [Фоновый runtime](runtime.md)
- [Известные ограничения](limitations.md)
- [Функции и CLI](functions.md)
- [Мастер-план](../todo/mydomo.md)
