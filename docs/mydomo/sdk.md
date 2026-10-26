---
title: MyDoMo — SDK модулей
audience: developer, agent
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
  "dependencies": ["mqtt"],
  "permissions": ["weather.view", "weather.manage"]
}
```

Обязательны `id`, `version`, `namespace`. `type` должен совпадать с
каталогом: `core` для `modules/core/<id>`, `module` для `modules/<id>`.
`requires` проверяется через semver; `dependencies` — id уже включённых
модулей. Пути относительные, без `..`.

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

## 4. Данные

`storage.tables` — логические имена. Физические таблицы получают префикс
`module_<id>_`, например `storage.tables: ["forecasts"]` →
`module_weather_forecasts`. Работать с ними можно только через
`ModuleDb::table('forecasts')`; чужие таблицы запрещены.

Миграции — `migrations/*.php`, файл возвращает `callable(Connection)`.
Они выполняются один раз при `module:install`; факт фиксируется в
`module_migrations`. Имена таблиц в миграции должны совпадать с
`ModuleDb::physicalName()` (или быть идемпотентными).

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

Состояние хранится в `module_registry`; при старте загружаются только
`enabled` сторонние модули. Выключенный модуль не регистрирует маршруты,
меню и helpers.

## 6. Безопасность и границы

- нет `eval`, `$db`, `SQLSelect*`, `mysqli_*`, `PDO` и глобальных путей ядра;
- только `ModuleDb`/`ModuleBus` и сервисы, полученные через контейнер;
- пользовательский ввод — через `Request`, CSRF на изменяющих маршрутах;
- `permissions` — декларация для будущего UI прав, сейчас не автоприменяется;
- assets модуля не подключаются глобально: страница сама решает, какой
  page-only CSS/JS ей нужен.

## 7. Ограничения текущего этапа

- установка только из локального `modules/<id>/`, marketplace позже;
- `module:remove` не удаляет таблицы и файлы;
- миграции не откатываются;
- `permissions` пока не связаны с `roles`/`permissions` автоматически;
- установка во время HTTP-запроса не поддерживается — только CLI.

## Связанные документы

- [Модульная система](modules.md)
- [Архитектура](architecture.md)
- [Функции и CLI](functions.md)
- [Мастер-план](../todo/mydomo.md)
