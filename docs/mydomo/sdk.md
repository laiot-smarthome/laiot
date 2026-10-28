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
`modules/core/<id>/` и всегда присутствуют; их можно выключить, но не
установить или удалить вручную. Сторонние устанавливаются через
`module:install` и живут по тем же правилам. Legacy `project_modules`,
uppercase-поля и `modules/<id>/<id>.class.php` не используются.

Готовый пример — [`modules/example_sdk`](../../modules/example_sdk/).

Ядровые страницы объектов, пользователей, журналов и API не являются
SDK-модулями: они находятся в `src`/`resources/views/pages` и перечислены
в [core-sections.md](core-sections.md). Каталог `modules/core` — для
встроенных интеграций; MQTT уже там ([mqtt.md](mqtt.md)), Zigbee и остальные
переносятся отдельно.

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
  "property_subscriber": "PropertySubscriber",
  "src": "src",
  "routes": "routes.php",
  "views": "views",
  "migrations": "migrations",
  "helpers": "helpers.php",
  "assets": "assets",
  "settings": {
    "host": {"title": "Хост", "type": "string", "default": "localhost"},
    "port": {"title": "Порт", "type": "int", "default": 1883},
    "mode": {
      "title": "Режим",
      "type": "select",
      "default": "auto",
      "options": {"auto": "Авто", "manual": "Вручную"}
    },
    "enabled": {"title": "Включено", "type": "bool", "default": true}
  },
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

`property_subscriber` — необязательный класс в namespace модуля, который
принимает изменения свойств (см. раздел 6). Какие именно свойства обслуживает
модуль, манифест не задаёт: это выбор пользователя.

`settings` — необязательная схема параметров модуля (см. раздел 5).
Значения хранит реестр, а не таблица модуля; экран общий для всех модулей.

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
`version()`, `settings()` и `subscriptions()`. Прямой доступ к PDO/`$db` ядра
запрещён.

### Yoyo в модуле

Модуль может зарегистрировать свои Yoyo-компоненты в `boot()`:

```php
\Clickfwd\Yoyo\Yoyo::registerComponent('weather-forecast', Yoyo\Forecast::class);
```

Шаблон такого компонента лежит в `views/` модуля и подключается через
namespace модуля (`weather::yoyo.forecast`); `render()` переопределяется,
потому что имя компонента — kebab-case, а файл — обычный Blade. Компонент
должен сам проверить, что модуль включён и у пользователя есть нужное
право: `/yoyo` закрыт только входом в сессию. Пример — MQTT:
`mqtt-topics`, `mqtt-status` в `modules/core/mqtt`.

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
паузы и рестарты — на стороне ядра. Исключение — интеграция с долгоживущим
соединением: MQTT держит задачу `serve` до своего `timeout`, а контекст
несёт `once=true` для `worker <id> --once`, чтобы разовый проход не висел
час. Подробно — [runtime.md](runtime.md), [mqtt.md](mqtt.md).

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

## 5. Настройки модуля

Настройки объявляются в манифесте, значения хранит `module_registry.settings`.
Модуль не создаёт свою таблицу для общих параметров и не рисует форму: экран
`/admin/modules/<id>/settings` строится по той же схеме, а модуль читает
готовые значения.

```json
"settings": {
  "host": {"title": "Хост", "type": "string", "default": "localhost"},
  "port": {"title": "Порт", "type": "int", "default": 1883},
  "mode": {
    "title": "Режим",
    "type": "select",
    "default": "auto",
    "options": {"auto": "Авто", "manual": "Вручную"}
  },
  "enabled": {"title": "Включено", "type": "bool", "default": true}
}
```

Типы: `string`, `text`, `int`, `bool`, `select`; `title` обязателен,
`description` необязателен. У `select` непустой `options`, а `default`
обязан быть одним из них. Ключи — `[a-z][a-z0-9_]`.

```php
$settings = $context->settings();
$host = $settings->get('host');   // умолчание схемы, если значение не сохранено
$port = $settings->get('port', 1883);
$all = $settings->all();          // только объявленные ключи
```

Сохранение идёт через общий экран: `ModuleSettings::cast()` проверяет типы,
не принимает неизвестное значение select и не сохраняет лишние поля.
Значения читаются при сборке контекста модуля, поэтому новый HTTP-запрос
видит их сразу, а запущенный worker нужно перезапустить. Произвольные данные,
которых нет в схеме, храните в `ModuleDb`, а не в настройках. Экран доступен
для установленного стороннего модуля; core-модули настроек через реестр не
имеют. Встроенный MQTT поэтому хранит параметры подключения в таблице
`module_mqtt_settings` и использует `ModuleSettings::cast()` для проверки
типов; своей формы проверки он не заводит ([mqtt.md](mqtt.md)).

## 6. Изменения свойств

Подписку на свойство выбирает пользователь. Манифест объявляет только
обработчик, а страница `/admin/modules/<id>/settings` показывает действующие
свойства объекта. Выбор хранит ядро в `property_subscriptions`, поэтому один
и тот же модуль в разных установках обслуживает разные устройства.

```json
"property_subscriber": "PropertySubscriber"
```

```php
use MajorDoMo\Events\PropertyChange;
use MajorDoMo\Modules\PropertySubscriber;

final class PropertySubscriber implements PropertySubscriber
{
    public function onPropertyChanged(PropertyChange $change): void
    {
        // отправить значение в устройство; ошибка попадёт в журнал
    }
}
```

Контракт: `objectTitle`, `propertyTitle`, `oldValue`, `newValue`, `source`,
`changed`, `chain`, `depth`. Запись значения не ждёт подписчика: изменение
уходит в очередь `module.<id>`, а обработчик вызывает worker модуля. Ошибка не
откатывает значение и не мешает другим модулям и OnChange. Повторная запись
того же свойства внутри обработчика останавливается цепочкой, а собственное
эхо модуль не получает: сервис записи не возвращает автору его же изменение.
Подробности — [events.md](events.md).

Подписки в коде модуля:

```php
$subscriptions = $context->subscriptions();
$subscriptions->attach($objectId, $propertyId);   // идемпотентно
$subscriptions->detach($objectId, $propertyId);
$subscriptions->isAttached($objectId, $propertyId);
$subscriptions->attached();                       // объект и свойство
```

Фасад привязан к id модуля из контекста: подписать соседний модуль нельзя,
а модуль без `property_subscriber` получает ошибку. Пара проверяется по
действующим свойствам объекта — объектное свойство или классовое с
наследованием. Прямые `INSERT` в `property_subscriptions` из модуля
запрещены.

## 7. Lifecycle

```bash
php bin/mydomo db:migrate          # создаёт module_registry/module_migrations
php bin/mydomo module:validate      # проверить все module.json
php bin/mydomo module:check [id]    # найти legacy-вызовы в коде модуля
php bin/mydomo module:list          # core + сторонние, installed/enabled
php bin/mydomo module:install weather
php bin/mydomo module:disable weather
php bin/mydomo module:enable weather
php bin/mydomo module:remove weather [--purge]
```

`module:install` проверяет semver, зависимости, выполняет миграции, вызывает
`install`/`enabled`, регистрирует разрешения из манифеста и публикует ассеты
в `public/assets/modules/<id>`. `module:enable` повторяет регистрацию прав и
публикацию. `module:remove` вызывает `uninstall`, убирает реестр, права
модуля (если они не выданы роли) и опубликованные ассеты. С `--purge` он
дополнительно удаляет объявленные таблицы и SQLite-данные; код модуля
остаётся. Core-модуль нельзя install/remove; disable/enable разрешены и
хранятся строкой реестра `enabled=0/1`.

disable вызывает hook disabled и затем записывает enabled=0;
enable повторно проверяет совместимость/зависимости и вызывает enabled.
Для core-модуля строка реестра создаётся при первом выключении, потому что
в поставке он считается включённым без записи.
Установка не является транзакцией вокруг произвольных DDL/hooks: при сбое
может остаться часть таблиц и применённых миграций. Изменение реестра
вступает в силу для маршрутов/меню при следующей сборке приложения;
долгоживущий worker нужно синхронизировать или перезапустить отдельно.

Состояние хранится в `module_registry`; при старте загружаются только
`enabled` сторонние модули. Выключенный модуль не регистрирует маршруты,
меню и helpers.

## 8. Безопасность и границы

- нет `eval`, `$db`, `SQLSelect*`, `mysqli_*`, `PDO` и глобальных путей ядра;
- `module:check` ищет эти вызовы в `src`, `routes` и `helpers` модуля и
  возвращает ненулевой код, если что-то найдено;
- только `ModuleDb`/`ModuleBus` и сервисы, полученные через контейнер;
- пользовательский ввод — через `Request`, CSRF на изменяющих маршрутах;
- `permissions` из манифеста регистрируются в таблице `permissions` с
  владельцем `module_id`; автоматически они не выдаются никому, кроме роли
  admin (у неё `*`). Остальным роль назначается вручную;
- ассеты публикуются при install/enable; сборка JS/CSS остаётся общей
  `npm run build`, библиотеки — через npm, без CDN и отдельных script/link.

Пункт «нет PDO/eval» — правило поддерживаемого кода, а не техническая
изоляция от произвольного PHP. `module:check` — сканер, а не sandbox;
автоматического CodeGuard пока нет.

## 9. Ограничения текущего этапа

- установка только из локального `modules/<id>/`, marketplace позже;
- `module:remove --purge` удаляет данные и таблицы, но не код модуля:
  версия кода живёт в пакете/каталоге `modules/`;
- миграции не откатываются;
- `permissions` регистрируются, но автоматической выдачи ролям нет;
- установка во время HTTP-запроса не поддерживается — только CLI.

## Связанные документы

- [Модульная система](modules.md)
- [Архитектура](architecture.md)
- [Фоновый runtime](runtime.md)
- [Известные ограничения](limitations.md)
- [Функции и CLI](functions.md)
- [Мастер-план](../todo/mydomo.md)

## Системные события

Подписка регистрируется в `boot()` entrypoint включённого модуля:

```php
public function boot(\MajorDoMo\Modules\ModuleContext $context): void
{
    $context->onSystemEvent('system.minute', function (string $event, array $data): void {
        // Реакция выполняется в worker этого модуля.
    });
}
```

Доступны `system.minute`, `system.hour` и `system.day`; `$data['occurred_at']`
содержит время границы с часовым поясом. Имя события показывает владельца:
ядро публикует только `system.*`, модуль — `module.<id>.*` (например,
`module.mqtt.ready`). Своё событие модуль отправляет через
`SystemEvents::emit('module.<id>.<имя>', $data)`; для событий состояния есть
`$context->bus()->publish()`, который сам добавляет префикс модуля и чужой
namespace не принимает. `SystemEvents::emit()` принимает обе формы, а
`$context->onSystemEvent()` позволяет подписаться на любую из них; чужое
имя модуля в префиксе не даёт прав на его таблицы и настройки.
События доставляются через `module.<id>`, поэтому необходимы работающие
`core-worker system-events` и `worker <id>`. Обработчики регистрируются при
каждом запуске приложения; после изменения модулей нужно перезапустить оба
процесса. Ошибки обрабатываются политикой повторов очереди; обработчики должны
допускать повторный вызов. Если подписка удалена до доставки, задание пропускается.
Это отдельный механизм от событий изменения свойств и ModuleBus.
События с любым из этих имён может слушать и сценарий: на странице
`/scripts` он привязывается к тому же имени события, а запускается через
очередь `default`. Подробнее — [фоновый runtime](runtime.md).
