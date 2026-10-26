---
title: "MyDoMo: мастер-план рефакторинга ядра и базовых модулей"
audience: user, agent
category: todo
tags: задачи, MyDoMo, рефакторинг, ядро, модули, Composer, архитектура, план
description: Пошаговый план перехода на современное ядро без обратной совместимости со старыми модулями, с пакетами Composer, этапами и критериями.
order: 5
---

# MyDoMo: мастер-план рефакторинга ядра и базовых модулей

Статус: **В работе**. Ветка: `MyDoMo`, создана от `alpha` (`0bc4c355`).
Дата начала: 2026-10-26.

Этот файл — единый маршрут переделки. Его нужно читать сверху вниз и отмечать
выполненное. Он заменяет прежние ограничения по обратной совместимости со
старыми модулями: в MyDoMo их больше нет. Отдельные заметки
[по ORM](eloquent-orm.md), [коллекциям](collections.md),
[Redis](redis-facade.md), [кэшу](cache-facade.md) и
[роутингу](routing.md) остаются источником идей, но их пункты «сохранить
старый модульный API» к MyDoMo не применяются.

## 1. Решение и границы

### 1.1. Что это значит

- `alpha` — последняя совместимая ветка. Она работает и остаётся точкой
  возврата. Критические исправления безопасности туда можно переносить, но
  новую архитектуру — нельзя.
- `MyDoMo` — новая мажорная версия. PHP-API старых модулей, `module.class.php`,
  `pd`, `view_mode`, `?action=...`, `/modules/*.php`, `jTemplate` и старые
  обёртки не являются контрактом. Их можно удалять по мере переписывания.
- Базовые модули входят в поставку и переписываются на новое ядро. Сторонние
  модули не обязаны работать; для них делается руководство по портированию и
  проверка старых вызовов.
- Сохраняются **данные**, а не код модулей: база, настройки, объекты, свойства,
  история, сценарии, методы, пользователи и файлы установки. Для них нужен
  путь обновления с резервной копией.
- Пользовательские сценарии и методы — отдельный контракт. Это не модули;
  ломать автоматизации без предупреждения нельзя. Для них сохраняется
  совместимый runtime (`gg`, `sg`, `callMethod`, `$this`, `SQLSelect*` и т. п.)
  либо предоставляется миграционный инструмент.
- Публичный HTTP API v1 — внешний контракт, а не модульный. Его желательно
  сохранить как тонкий фасад над новыми сервисами; ломающие изменения — в v2.
  Это решение можно отменить только явно.

### 1.2. Границы первого прохода

Входит:

- новое ядро: контейнер, HTTP, роутинг, БД, кэш, очереди, события, шаблоны,
  CLI, логирование, ошибки, безопасность, тесты;
- новый модульный SDK и жизненный цикл;
- переписывание всех базовых модулей;
- миграция данных с alpha;
- новая документация и CI.

Не входит в первый проход:

- полноценная изоляция исполнения модулей (контейнеры, отдельные пользователи,
  OS-границы);
- маркет и установка произвольных сторонних пакетов без проверки;
- мобильное приложение и полный редизайн интерфейса;
- замена MariaDB, MQTT, Workerman и Neuron AI;
- поддержка старых модулей через compatibility layer.

Изоляция модулей проектируется так, чтобы её можно было добавить позже, но
обещанием первого релиза не является. До неё модули считаются доверенными:
манифест и permissions — декларации для UI и будущей проверки, а не песочница.

## 2. Что уже есть

Сильные стороны, которые нужно сохранить:

- доменная модель MajorDoMo: классы, объекты, свойства, методы, сценарии,
  события, задания, история;
- `Core::*` и сервисы в `lib/core/` уже разделены по ответственности;
- долговечная очередь `core_runtime`, полосы, подтверждения и повторы;
- событийный журнал `system_events` с доставкой подписчикам;
- MQTT-быстрый путь, Zigbee, Workerman WebSocket, Neuron AI;
- установщик, миграции, режим обслуживания, деплой, systemd;
- дизайн-система `MDJUI` + `md-*`, Vite, Bootstrap 5, тёмная тема;
- 91 smoke/regression-тест и изолированные проверки БД/Redis;
- документация для агентов и база знаний в админке.

Что мешает и что переделываем:

| Проблема | Следствие | Решение |
| --- | --- | --- |
| Два мира: `lib/*.php` и `lib/core/` | Дублирование, старые глобалы, непонятный вход | Новый `src/`, старые файлы удаляются по карте |
| `module.class.php` и `view_mode` | Модуль сам разбирает URL и рисует страницу | Контроллеры, routes, Blade, SDK |
| Статическая таблица `admin_router.php` | Маршрут модуля требует правки ядра | Роутер и `module.json` |
| `pd`-состояние | URL и навигация завязаны на legacy | Чистые URL и именованные маршруты |
| Самописные SQL-обёртки | Нет типов, транзакции разделены | PDO/Eloquent, репозитории, миграции |
| Кэш без TTL и типов | Нельзя хранить массивы и `false` | Cache-абстракция с TTL и типами |
| Самописные шаблоны | Смешение PHP и разметки | Blade для нового UI |
| Нет CI и статического анализа | Регрессии ловятся поздно | PHPUnit, PHPStan, Rector, PHP-CS-Fixer, CI |
| Нет формального SDK модулей | Каждый модуль уникален | Манифест, provider, routes, migrations, tests |

## 3. Целевая архитектура

### 3.1. Слои

```text
public/index.php            единый HTTP-front controller
  └─ src/Http/Kernel.php    Request → middleware → Router → Controller → Response
       ├─ src/Routing/      маршруты, имена, middleware, кэш
       ├─ src/Http/         Request/Response, middleware, исключения
       ├─ src/Modules/      реестр модулей, провайдеры, lifecycle
       └─ src/Core/         доменные сервисы: Objects, Properties, Methods...
            ├─ src/Database/  Eloquent-модели, репозитории, миграции
            ├─ src/Cache/     PSR-16-совместимый слой
            ├─ src/Queue/     долговечная очередь и воркеры
            ├─ src/Events/    журнал событий и локальные слушатели
            └─ src/Support/   конфиг, логи, время, файлы, ID, ошибки

modules/<id>/               модуль как пакет
  ├─ module.json            манифест
  ├─ src/                   провайдер, контроллеры, сервисы, модели
  ├─ routes/web.php         панельные маршруты
  ├─ routes/api.php         API-маршруты
  ├─ migrations/            миграции модуля
  ├─ resources/views/       Blade-шаблоны
  ├─ resources/assets/      исходники CSS/JS
  ├─ tests/                 тесты модуля
  └─ docs/                  документация модуля
```

`src/` — новый код. `lib/` и `templates/` — временный слой на время переноса;
после переписывания базовых модулей они удаляются. Namespace остаётся
`MajorDoMo\Core` / `MajorDoMo\Modules\...`, чтобы не устраивать отдельный
ребрендинг кода.

### 3.2. Один запрос

1. `public/index.php` подключает Composer и `bootstrap/app.php`.
2. `Application` собирает контейнер, конфиг, логгер, БД, кэш, сессию.
3. `Http\Kernel` превращает запрос в `Illuminate\Http\Request`.
4. Глобальные middleware: install gate, maintenance, auth, CSRF, rate limit,
   request id, locale/timezone.
5. `Router` находит маршрут; ошибки 404/405/500 формируются единообразно.
6. Контроллер вызывает сервис/репозиторий, возвращает `Response` или `View`.
7. События после commit; очередь после commit; ответ уходит через middleware.
8. Ошибки логируются в Monolog с `request_id`, `trace_id`, маршрутом и модулем.

Долгие операции (циклы, MQTT, очередь, WebSocket) не живут внутри HTTP-запроса.
Они запускаются отдельными командами/воркерами и используют те же сервисы.

### 3.3. Один модуль

1. Ядро сканирует `modules/*/module.json` и проверяет схему.
2. Для включённого модуля регистрируется provider и его маршруты.
3. Модуль объявляет миграции, меню, настройки, права, ассеты и документацию.
4. Установка/обновление/отключение/удаление идут через `ModuleManager`.
5. Кэш маршрутов, меню и настроек инвалидируется при каждом изменении.
6. `module:list`, `module:enable`, `module:migrate`, `module:remove` —
   обязательные CLI-команды.

## 4. Стек и Composer

### 4.1. PHP

Рекомендация: **PHP >= 8.2**, целевая версия production — 8.3, локально 8.4.
Причина: Illuminate 12 и Symfony 7.4 требуют 8.2; PHPUnit 11 — 8.2.
Если обязательно нужен PHP 8.1, современный стек откатывается к Symfony 6.4
и Illuminate 10, что противоречит цели «современное ядро». Это решение нужно
подтвердить до установки пакетов.

В `composer.json`:

```json
"require": {
  "php": ">=8.2"
},
"config": {
  "platform": {
    "php": "8.2.0"
  }
}
```

CI проверяет 8.2 и 8.3; локальная 8.4 не должна давать новых предупреждений.

### 4.2. Рекомендуемый стек

**Illuminate 12 как набор компонентов, не полный Laravel.** Это даёт
Laravel-роутинг, Eloquent, коллекции, кэш, Blade и контейнер, но сохраняет
собственные entrypoints, циклы, очередь, MQTT и WebSocket. Полный
`laravel/framework` в первом проходе не берём: он заменит слишком много
работающих механизмов и заставит переносить фоновые процессы в чужой lifecycle.

| Пакет | Версия | Зачем | Решение |
| --- | --- | --- | --- |
| `illuminate/routing` | `^12.0` | Маршруты, группы, middleware, имена | Берём |
| `illuminate/http` | `^12.0` | Request/Response, middleware | Берём |
| `illuminate/container` | `^12.0` | DI-контейнер | Берём |
| `illuminate/pipeline` | `^12.0` | Конвейер middleware | Транзитивно |
| `illuminate/database` | `^12.0` | Eloquent, Query Builder, Schema | Берём |
| `illuminate/collections` | `^12.0` | Коллекции | Берём |
| `illuminate/cache` | `^12.0` | Кэш с TTL и store-абстракцией | Берём |
| `illuminate/redis` | `^12.0` | Redis-store | Берём |
| `illuminate/view` | `^12.0` | Blade | Берём |
| `illuminate/filesystem` | `^12.0` | Blade и файлы | Транзитивно |
| `illuminate/events` | `^12.0` | События view/container | Транзитивно |
| `predis/predis` | `^2.4` | Redis без ext-redis | Берём как fallback |
| `monolog/monolog` | `^3.9` | Логи и каналы | Берём |
| `symfony/http-client` | `^7.4` | Исходящие HTTP-запросы | Берём |
| `symfony/rate-limiter` | `^7.4` | Ограничение частоты | Берём |
| `symfony/process` | `^7.4` | Процессы воркеров | Берём/транзитивно |
| `symfony/uid` | `^7.4` | ID/trace при необходимости | Оценить |
| `zircote/swagger-php` | `^5.0` | OpenAPI из атрибутов | Оценить |
| `league/commonmark` | `^2.7` | Markdown базы знаний | Оценить |
| `dragonmantank/cron-expression` | `^3.4` | Cron-расписания, если появятся | Оценить |
| `illuminate/console` | `^12.0` | CLI на Symfony Console | Позже |
| `illuminate/validation` | `^12.0` | Валидация API и форм | Позже |
| `illuminate/translation` | `^12.0` | Сообщения валидации | Вместе с validation |
| `php-di/php-di` | `^7.1` | Альтернативный DI | Не берём: есть Illuminate Container |
| `symfony/routing` | `^7.4` | Транзитивно к Illuminate | Не дублируем |
| `guzzlehttp/guzzle` | `^7.9` | Альтернатива HTTP-клиенту | Не берём, если есть HttpClient |
| `twig/twig` | `^3.0` | Альтернатива Blade | Не берём |
| `doctrine/dbal` | `^3.10` | Альтернатива Eloquent/Schema | Не берём |
| `laravel/framework` | `^12.0` | Полный Laravel | Не берём в первом проходе |
| `vlucas/phpdotenv` | — | `.env` | Не берём: Config уже есть |
| `league/flysystem` | — | Файлы | Не берём без необходимости |
| `sentry/sdk` | — | Мониторинг ошибок | Опционально позже |

Правила выбора: одна библиотека на задачу, активная поддержка, PHP 8.2+,
MIT/BSD, отсутствие известных уязвимостей, разумный вес. Все версии
фиксируются в `composer.lock`; обновление — отдельной задачей с `composer audit`.

### 4.3. Инструменты разработки

| Пакет | Версия | Зачем |
| --- | --- | --- |
| `phpunit/phpunit` | `^11.5` | Unit/Feature/Integration |
| `mockery/mockery` | `^1.6` | Моки границ |
| `fakerphp/faker` | `^1.24` | Фабрики и фикстуры |
| `phpstan/phpstan` | `^2.1` | Статический анализ |
| `phpstan/phpstan-strict-rules` | `^2.0` | Строгие правила |
| `phpstan/phpstan-deprecation-rules` | `^2.0` | Контроль deprecated |
| `rector/rector` | `^2.7` | Автоматический рефакторинг |
| `friendsofphp/php-cs-fixer` | `^3.95` | Единый стиль |
| `ergebnis/composer-normalize` | `^2.54` | Порядок в composer.json |
| `php-parallel-lint/php-parallel-lint` | `^1.4` | Быстрый lint |
| `roave/security-advisories` | `dev-latest` | Запрет уязвимых версий |
| `symfony/var-dumper` | `^7.4` | Отладка в dev |
| `symfony/browser-kit` | `^7.4` | HTTP-тесты, если понадобятся |
| `symfony/dom-crawler` | `^7.4` | Разбор HTML в тестах |
| `symfony/css-selector` | `^7.4` | CSS-селекторы в тестах |

Не берём `pestphp/pest` (достаточно PHPUnit), `infection/infection` (позже),
`phpbench/phpbench` (после стабилизации), `filp/whoops` (свой error handler).

### 4.4. Команды установки (spike)

Не выполнять все сразу. Сначала spike на отдельной ветке:

```bash
composer require illuminate/routing:^12.0 illuminate/http:^12.0 \
  illuminate/container:^12.0 illuminate/database:^12.0 \
  illuminate/collections:^12.0 illuminate/cache:^12.0 \
  illuminate/redis:^12.0 illuminate/view:^12.0
composer require predis/predis:^2.4 monolog/monolog:^3.9 \
  symfony/http-client:^7.4 symfony/rate-limiter:^7.4 symfony/process:^7.4
composer require --dev phpunit/phpunit:^11.5 mockery/mockery:^1.6 \
  fakerphp/faker:^1.24 phpstan/phpstan:^2.1 \
  phpstan/phpstan-strict-rules:^2.0 phpstan/phpstan-deprecation-rules:^2.0 \
  rector/rector:^2.7 friendsofphp/php-cs-fixer:^3.95 \
  ergebnis/composer-normalize:^2.54 php-parallel-lint/php-parallel-lint:^1.4 \
  roave/security-advisories:dev-latest symfony/var-dumper:^7.4
```

Spike считается успешным, если одновременно работают: контейнер, один
маршрут, Blade-шаблон, Eloquent-модель, кэш, логгер, тест и `composer
check-platform-reqs` на PHP 8.2. Если склейка занимает больше двух недель,
возвращаемся к решению о полном Laravel или Symfony 7.4.

## 5. Этапы

| Этап | Результат | Ветка | Гейт |
| --- | --- | --- | --- |
| 0 | Решения, ветка, CI, инструменты | `mydomo/foundation` | CI зелёный, spike работает |
| 1 | Каркас: контейнер, конфиг, логи, ошибки, БД | `mydomo/foundation` | Один запрос и одна модель |
| 2 | HTTP, роутер, middleware, auth, Blade | `mydomo/http` | `/panel/` и API-эндпоинт |
| 3 | Данные: Eloquent, репозитории, миграции | `mydomo/data` | CRUD объекта и свойства |
| 4 | Кэш, очередь, события, планировщик | `mydomo/runtime` | Операция через очередь |
| 5 | Модульный SDK и lifecycle | `mydomo/modules` | Фикстурный модуль |
| 6 | Базовые модули, волна 1 | `mydomo/module-*` | Панель, классы, объекты |
| 7 | Базовые модули, волна 2 | `mydomo/module-*` | Скрипты, события, пользователи |
| 8 | Базовые модули, волна 3 | `mydomo/module-*` | Настройки, ошибки, задания |
| 9 | Интеграции и тяжёлые модули | `mydomo/module-*` | MQTT, Zigbee, AI, market |
| 10 | Удаление legacy, миграция, релиз | `mydomo/release` | Чистая установка и upgrade |

Ветка `MyDoMo` — интеграционная. Каждый этап приходит отдельным PR, CI
обязателен. После этапа 2 ветка должна запускаться, даже если часть страниц
ещё на старом коде: переходный период допускается только через явный
feature flag, а не через вечную двойную поддержку.

## 6. Детальный чек-лист по ядру

### 6.1. Фундамент и каркас

- [ ] Зафиксировать решение по PHP 8.2+ и стеку в `docs/agents/architecture.md`.
- [ ] Создать `src/`, `config/`, `routes/`, `database/`, `tests/Unit`,
  `tests/Feature`, `tests/Integration`.
- [ ] Обновить `composer.json`: PSR-4 для `src/`, `require-dev`, scripts,
  platform 8.2, `sort-packages`.
- [ ] Добавить `.php-cs-fixer.dist.php`, `phpstan.neon`, `rector.php`,
  `phpunit.xml`.
- [ ] Переписать `bootstrap/app.php`: окружение, контейнер, конфиг, логгер,
  обработчик ошибок, БД, кэш, сессия.
- [ ] Определить `Application`/`Kernel`: boot, handle, terminate, console.
- [ ] Ввести `RequestId`/`TraceId` и единый контекст логов.
- [ ] Добавить `config/*.php` с типизированными значениями; `.env` остаётся
  источником, `Config` — только загрузчик и валидатор.
- [ ] Добавить health-check: `/health` (БД, Redis, очередь, версия).
- [ ] Добавить `php majordomo about` с версией MyDoMo, PHP, БД и модулей.
- [ ] Проверить, что старый `Core::*` может быть фасадом над контейнером
  без двойной инициализации.

### 6.2. HTTP и роутинг

- [ ] `public/index.php` — единый front controller; `public/api.php`,
  `public/admin.php`, `public/nf.php` убрать или оставить только редиректы.
- [ ] `public/install.php` оставить отдельным входом: база может быть пустой.
- [ ] `src/Http/Kernel.php`: middleware pipeline, исключения, content
  negotiation, HTML/JSON 404/405/500.
- [ ] `src/Routing/Router.php`: `get/post/put/patch/delete`, группы, параметры,
  `where`, имена, `route()`, `redirect()`, `back()`.
- [ ] Middleware: `InstallGate`, `Maintenance`, `AuthenticateAdmin`,
  `VerifyCsrf`, `Throttle`, `SetLocale`, `RequestId`, `SecurityHeaders`.
- [ ] Роуты ядра: `/`, `/panel/...`, `/api/v1/...`, `/api/v2/...`, `/install`,
  `/health`, `/ws`.
- [ ] Роуты модулей: `/panel/<id>/...`, `/api/v2/modules/<id>/...`;
  автоматический префикс и проверка владельца.
- [ ] Кэш маршрутов: `php majordomo route:cache`, `route:clear`, проверка
  конфликтов и запрет замыканий в кэшируемых маршрутах.
- [ ] 404/405/500: единый HTML для панели и JSON для API; `Allow` для 405.
- [ ] Запретить GET-мутации; CSRF для `POST/PUT/PATCH/DELETE`.
- [ ] Удалить `pd`, `view_mode`, `?action=...` из нового ядра.
- [ ] Добавить тонкий слой редиректов только для самых популярных старых
  URL, если это дешево; полная совместимость не требуется.

### 6.3. Данные и Eloquent

- [ ] Перевести соединение на PDO (`pdo_mysql`), убрать `mysqli` из нового
  слоя; старый слой — только на время переноса.
- [ ] Настроить `Illuminate\Database\Capsule\Manager` или собственный
  `DatabaseManager` с одним соединением на запрос/воркер.
- [ ] Создать базовую модель `src/Database/Model.php`: `$timestamps=false`,
  `$primaryKey='ID'`, `$keyType='int'`, `$incrementing=true`.
- [ ] Модели ядра: `ObjectModel`, `ClassModel`, `PropertyModel`,
  `MethodModel`, `PropertyValue`, `Script`, `Event`, `Job`, `User`,
  `Setting`, `Module`, `History`.
- [ ] Репозитории для сложных выборок; контроллеры не пишут SQL.
- [ ] Сохранить таблицы и поля alpha; новые таблицы — с префиксом `mydomo_`
  или `module_<id>_`.
- [ ] Миграции: базовый снимок схемы + инкрементальные файлы; отдельный
  `Migrator` с журналом и транзакциями.
- [ ] Перевести горячие таблицы в InnoDB, затем `utf8mb4`; делать пакетно,
  с резервной копией и проверкой внешних ключей.
- [ ] Добавить индексы под новые запросы; убрать N+1 через eager loading.
- [ ] Транзакции — на границе сервиса; события и очередь — после commit.
- [ ] Сохранить `DB_PROTECT_DROP` для raw SQL или явно заменить политикой
  миграций.
- [ ] Проверить типы `NULL`, `0`, `false`, пустых строк и дат на реальном
  дампе alpha.
- [ ] Подготовить `mydomo:db:check` — сравнение схемы alpha и MyDoMo.

### 6.4. Кэш

- [ ] Ввести `CacheInterface` (PSR-16) и фабрику stores: Redis, database,
  file, array (tests).
- [ ] Namespace: `mydomo:{install_hash}:{area}:`; ключи не пересекаются между
  свойствами, метаданными, сессиями, очередями и модулями.
- [ ] Поддержать TTL, `remember`, `has`, `forget`, `clearPrefix`, типы
  (массивы, `false`, `0`, `null`).
- [ ] Запретить кэширование внутри незавершённой транзакции; инвалидация
  после commit.
- [ ] Не использовать `FLUSHDB`/`FLUSHALL`; только SCAN по префиксу.
- [ ] Redis опционален; без него — database/file store с теми же гарантиями.
- [ ] `predis/predis` — fallback, если ext-redis нет; предпочитать phpredis.
- [ ] CLI: `cache:clear`, `cache:stats`, `cache:warm`.
- [ ] Метрики hit/miss и размера в health-check.

### 6.5. Очередь, события, планировщик

- [ ] Сохранить долговечный журнал и Redis-быстрый путь; вынести интерфейсы
  `Queue`, `Job`, `JobHandler`, `EventBus`.
- [ ] Типизированные задания вместо произвольных строк; валидация payload.
- [ ] Идемпотентность, ключи повторов, dead-letter, лимиты попыток.
- [ ] Полосы `object:<id>` и `serial` сохранить; они уже доказали порядок.
- [ ] События: append-only `system_events`, PSR-14 для локальных слушателей,
  доставка модулям через очередь, outbox после commit.
- [ ] Планировщик: задания, повторы, отмена, trace; cron-expression только
  если появятся cron-правила.
- [ ] CLI: `queue:status`, `queue:retry`, `queue:failed`, `jobs:list`,
  `worker:start`, `worker:stop`.
- [ ] Метрики: глубина, задержка, ошибки, возраст старейшего задания.
- [ ] Тесты: конкурентные воркеры, потеря соединения, повтор после сбоя,
  отсутствие двойного выполнения на уровне handlers.

### 6.6. Модульный SDK

- [ ] `module.json`: id, name, version, requires, provider, routes,
  migrations, views, assets, menu, permissions, settings, docs, ai.
- [ ] Валидация манифеста и запрет установки без обязательных полей.
- [ ] `ModuleManager`: discover, install, enable, disable, upgrade, remove.
- [ ] `ModuleServiceProvider`: register, boot, routes, migrations, menu.
- [ ] Реестр модулей в БД (`modules`), миграция из `project_modules`.
- [ ] Маршруты модуля загружаются только для включённого модуля; кэш
  инвалидируется при lifecycle.
- [ ] Настройки модуля — схема + `module_settings`; без прямых записей в
  `settings`.
- [ ] Меню и права — из манифеста; проверка прав на сервере.
- [ ] Ассеты: сборка core Vite или публикация в `public/assets/modules/<id>/`.
- [ ] Документация модуля — `modules/<id>/docs/`, интеграция с базой знаний.
- [ ] Тесты модуля — `modules/<id>/tests/`, запуск в CI.
- [ ] CLI: `module:list`, `module:enable`, `module:disable`, `module:migrate`,
  `module:remove`, `module:make`.
- [ ] `module:check` — сканер старых вызовов (`module.class.php`, `view_mode`,
  `pd`, `$this->action`) для портирования сторонних модулей.
- [ ] Политика зависимостей: сначала только core vendor; позже — собственный
  `composer.json` модуля; изоляция — отдельный этап.

### 6.7. Шаблоны и UI

- [ ] Blade-лейаут `resources/views/layouts/panel.blade.php`, компоненты
  `resources/views/components/`.
- [ ] Перенести `MDJUI` в Blade-компоненты или оставить PHP-хелперы,
  вызываемые из Blade; не держать две разметки.
- [ ] Сохранить `md-*`, Bootstrap 5, тёмную тему, токены и мобильную вёрстку.
- [ ] Заменить `partLoad` на `fetch` + History API или HTMX; решение — после
  spike, но jQuery в новом коде не используем.
- [ ] Удалить `jTemplate`/`parser` после переноса базовых модулей.
- [ ] Ассеты: Vite, один core entry + page entries; без новых `<script>` в
  шаблонах.
- [ ] Проверять 390 px, светлую/тёмную тему, клавиатуру и `prefers-reduced-motion`.

### 6.8. CLI

- [ ] Сохранить `php majordomo` и имена существующих команд, где возможно.
- [ ] Решить: собственный Console или Symfony/Illuminate Console; spike после
  этапа 2. До решения новые команды — в текущем стиле.
- [ ] Команды: `about`, `doctor`, `install`, `upgrade`, `db:migrate`,
  `db:seed`, `route:list`, `route:cache`, `route:clear`, `cache:*`,
  `queue:*`, `module:*`, `user:*`, `maintenance:*`.
- [ ] JSON-вывод, коды завершения, `--no-interaction`, `--verbose`.
- [ ] Не добавлять новые самостоятельные CLI-скрипты в корне.

### 6.9. Логи, ошибки, безопасность

- [ ] Monolog: каналы `app`, `http`, `api`, `db`, `queue`, `module`,
  `security`; ротация и уровень из `.env`.
- [ ] Сохранить `DebMes` как фасад; новые логи — только через PSR-3.
- [ ] Единый error handler: HTML/JSON, error id, без stack trace в production.
- [ ] `RequestId`/`TraceId` во всех логах и ответах.
- [ ] Пароли: `password_hash` с `PASSWORD_ARGON2ID` или bcrypt; перехеширование
  при входе.
- [ ] Сессии: `HttpOnly`, `Secure`, `SameSite=Strict`, regenerate при входе.
- [ ] CSRF для небезопасных методов; никаких GET-мутаций.
- [ ] Rate limit для логина, API, публичных маршрутов и тяжёлых действий.
- [ ] Токены API — только хеш в БД, ротация, отзыв, срок.
- [ ] Секреты модулей — шифрование sodium, ключ вне БД.
- [ ] Валидация загрузок, путей, JSON, размеров тела; запрет `..` и null-byte.
- [ ] `composer audit` и `roave/security-advisories` в CI.
- [ ] Политика прав: роли/разрешения, проверка на сервере, а не в UI.

### 6.10. Конфигурация, установка, обновление

- [ ] Сохранить `.env` + `Config`, добавить типизированные объекты и
  валидацию обязательных ключей.
- [ ] `config/app.php`, `config/database.php`, `config/cache.php`,
  `config/queue.php`, `config/logging.php`, `config/modules.php`.
- [ ] Установщик: PDO, проверка PHP 8.2/расширений, utf8mb4, Redis optional.
- [ ] `mydomo:install` для CLI; `public/install.php` для браузера.
- [ ] `mydomo:upgrade <backup>`: проверка версии alpha, backup, миграции,
  пересборка кэша, проверка здоровья.
- [ ] Режим обслуживания и откат; journal обновления.
- [ ] Не удалять старые таблицы/колонки в первом релизе; только additive.

### 6.11. Тесты и качество

- [ ] PHPUnit: `tests/Unit`, `tests/Feature`, `tests/Integration`.
- [ ] Сохранить smoke-тесты как быстрые контрактные проверки; постепенно
  перевести ключевые в PHPUnit.
- [ ] Тестовая БД — MariaDB в Docker/CI; SQLite только для чистых unit.
- [ ] Redis-тест с отдельным namespace; без `FLUSHDB`.
- [ ] Фабрики/Faker для объектов, свойств, сценариев, модулей.
- [ ] Контрактные тесты API v1 и v2; 404/405/CSRF/rate limit.
- [ ] PHPStan: уровень 6+ для нового кода, baseline для старого; позже 8.
- [ ] Rector: dry-run в CI, автофиксы отдельными PR.
- [ ] PHP-CS-Fixer: PSR-12 + правила проекта; `--dry-run` в CI.
- [ ] `php -l`/`parallel-lint`, `node --check`, `npm run build`.
- [ ] Покрытие: не гнаться за 100%; цель — 70% нового кода и все критичные
  сценарии (свойства, очередь, auth, API, модули).

## 7. Базовые модули

### 7.1. Порядок переписывания

| Волна | Модули | Почему |
| --- | --- | --- |
| 1 | `panel`, `control_modules`, `users`, `settings` | Каркас, вход, права, конфиг |
| 2 | `classes`, `objects`, `properties`, `methods` | Основа доменной модели |
| 3 | `scripts`, `events`, `history`, `pvalues`, `locations`, `pinghosts` | Автоматизация и данные |
| 4 | `system_errors`, `actions_log`, `jobs`, `linkedobject`, `ui_kit`, `shoutbox` | Поддержка UI и диагностики |
| 5 | `api`, `xray`, `market`, `saverestore` | Тяжёлые административные модули |
| 6 | `mqtt`, `zigbee`, `modes`, `knowledge_base`, `blockly_code`, `ai_assistant` | Интеграции и ИИ |

`mqtt` и `zigbee` зависят от очереди/событий; `market` — от module SDK;
`saverestore` — от миграций и деплоя; `ai_assistant` — от базы знаний.
Поэтому они идут после фундамента, а не «когда дойдут руки».

### 7.2. Чек-лист каждого модуля

- [ ] `module.json` с id, версией, provider, зависимостями.
- [ ] `src/` PSR-4, без глобальных функций и `module.class.php`.
- [ ] `routes/web.php` и `routes/api.php`; имена маршрутов с префиксом модуля.
- [ ] Контроллеры возвращают `View`/`Response`, не печатают HTML.
- [ ] Сервисы/репозитории; SQL только в репозиториях.
- [ ] Blade-шаблоны; `md-*`/`MDJUI`; без jQuery.
- [ ] Миграции модуля; обратимость или явный backup.
- [ ] Настройки — схема, права — манифест, меню — манифест.
- [ ] Ассеты — `resources/assets` + Vite/public.
- [ ] `tests/` модуля: happy path, права, ошибки, пустые данные.
- [ ] `docs/` модуля и запись в базе знаний.
- [ ] Удаление/отключение не оставляет маршрутов, меню и битых ссылок.
- [ ] Проверка на PHP 8.2/8.3, MariaDB, Redis и без Redis.

### 7.3. Особые случаи

- `panel` и `control_modules` — первыми: без них нельзя проверить меню,
  права и module lifecycle.
- `users` — после auth, но до модулей, которые проверяют права.
- `classes`/`objects`/`properties`/`methods` — это ядро домена; здесь нельзя
  допускать N+1 и потери истории/аудита.
- `scripts` и `events` — пользовательский PHP; сохранить runtime-совместимость
  и предупреждения о старых вызовах.
- `xray`, `mqtt`, `saverestore`, `market` — самые большие; их нельзя
  переписывать «заодно» с ядром, только отдельными этапами.
- `ai_assistant` уже имеет `src/` и документацию; его можно взять как образец
  нового модуля, но API ядра всё равно изменится.

## 8. Совместимость данных и пользовательского кода

### 8.1. База

- Сохранить имена таблиц и колонок alpha на первый релиз.
- Новые таблицы — `mydomo_*` или `module_<id>_*`; не переименовывать старые
  без отдельной миграции.
- Перед миграцией — полный backup и проверка свободного места.
- InnoDB/utf8mb4 — пакетно, с проверкой после каждой таблицы.
- Внешние ключи — только после очистки сирот и проверки на копии.
- `project_modules` → `modules`; данные модулей не терять.
- `settings` сохранить как таблицу пользовательских настроек; новые
  конфиги — в `config/`/`.env`.
- `cms/modules_installed` — оставить для install gate или заменить
  `modules`-реестром; решить до этапа 5.

### 8.2. Сценарии и методы

- Сохранить `gg`, `sg`, `getGlobal`, `setGlobal`, `callMethod`,
  `callMethodSafe`, `say`, `runScript`, `SQLSelect*` как фасады над новыми
  сервисами.
- `$this` в методах остаётся объектом MajorDoMo.
- Старые сценарии не переписывать автоматически; предоставить отчёт
  `legacy:scan` с найденными вызовами.
- Ошибки в старом коде не должны ронять весь новый runtime; логировать с
  trace и показывать в «Ошибках системы».
- После стабилизации — отдельный проект миграции пользовательского кода,
  не в первом проходе.

### 8.3. API

- `/api/v1` — сохранить контракт и конверт `{ok,data,meta}` / `{ok,error,meta}`
  как compatibility island.
- `/api/v2` — новый контракт; модульные маршруты только там.
- OpenAPI: v1 — текущий `Contract` или снимок; v2 — атрибуты/схемы.
- Токены, локальная сеть, CORS и panel-сессии — переиспользовать логику
  `AccessPolicy`/`AuthGate`, переписав на новые сервисы.
- Старый `/api/v1/module/{module}` можно оставить только как заглушку с
  понятной ошибкой; новые модули его не используют.

### 8.4. URL

- Новые URL — чистые, без `pd` и `view_mode`.
- Старые `/panel/...` можно поддержать редиректами, но не полной таблицей
  regex. Это не контракт модулей.
- 404 — честный, без catch-all.
- `ROOTHTML`/подкаталог установки — обязательный тест.

## 9. CI и локальная проверка

### 9.1. CI

Добавить `.github/workflows/ci.yml` (или аналог) с матрицей PHP 8.2/8.3:

- `composer validate --strict`, `composer normalize --dry-run`,
  `composer install`, `composer audit`;
- `parallel-lint`, `php-cs-fixer --dry-run`, `phpstan`, `rector --dry-run`;
- `phpunit` с MariaDB 11.4 и Redis 7.4;
- smoke-тесты: routing/config/api/auth/core_runtime;
- `npm ci`, `npm run build`, `node --check`;
- артефакты покрытия и логов.

Кэшировать `vendor/`, `node_modules/`, Composer/npm cache. Не использовать
рабочую БД и рабочую `.env`.

### 9.2. Локально

```bash
composer install
npm ci
npm run build
php majordomo about
php majordomo doctor
phpunit
phpstan analyse
php-cs-fixer fix --dry-run --diff
rector process --dry-run
php tests/routing_smoke.php
php tests/config_smoke.php
```

Для MariaDB/Redis — docker-стенд только для отладки. Windows-особенности в
рабочий код не добавлять. Linux-механизмы проверять в контейнере.

## 10. Документация

- [ ] Переписать `AGENTS.md` и `docs/agents/AGENTS.md` под MyDoMo: PHP 8.2,
  новый стек, запрет старых модульных API, новые правила.
- [ ] `docs/agents/architecture.md` — слои, boot, контейнер, lifecycle.
- [ ] `docs/agents/routing.md` — новый роутер, middleware, имена, кэш.
- [ ] `docs/agents/modules.md` — SDK, манифест, lifecycle, портирование.
- [ ] `docs/agents/database.md` — Eloquent, репозитории, миграции, InnoDB.
- [ ] `docs/agents/cache.md`, `queue.md`, `events.md`.
- [ ] `docs/agents/testing.md` — PHPUnit, CI, фикстуры.
- [ ] `docs/agents/upgrade.md` — alpha → MyDoMo, backup, откат.
- [ ] `docs/user/upgrade.md` — инструкция пользователю.
- [ ] Обновить `docs/README.md`, `docs/todo/README.md`, базу знаний.
- [ ] Старые `legacy-module-migration.md` и `admin-routing.md` пометить как
  alpha-only или удалить после релиза.

## 11. Риски и откат

| Риск | Вероятность | Последствие | Мера |
| --- | --- | --- | --- |
| Слишком много изменений сразу | Высокая | Ветка не запускается | Walking skeleton, этапы, CI |
| Eloquent не ложится на старую схему | Средняя | Потеря данных/тормоза | Spike, модели, миграции, тест на дампе |
| PHP 8.2 не доступен на хостинге | Средняя | Не установится | Проверить до старта; alpha остаётся |
| Blade-перенос затягивается | Средняя | UI застревает | Сначала shell и 2 модуля, потом волны |
| Модульный SDK слишком сложен | Средняя | Сторонние модули не портируются | Минимальный манифест, потом расширения |
| Потеря пользовательских сценариев | Высокая | Сломанные автоматизации | Compatibility runtime + scan + backup |
| API v1 сломается | Средняя | Отвалятся интеграции | Compatibility island и контрактные тесты |
| Нет изоляции модулей | Высокая | Риск для системы | Доверенные модули, permissions, будущая изоляция |
| Долгая миграция InnoDB | Средняя | Даунтайм | Пакетно, backup, maintenance, окно |

Откат:

- тег/ветка `alpha` и резервная копия БД;
- миграции только additive до релиза;
- `MYDOMO_NEXT=1` на время разработки, старый вход остаётся;
- после релиза — обратная миграция только через backup, не через downgrade
  схемы;
- destructive-изменения (удаление колонок/таблиц) — не раньше следующего
  мажора.

## 12. Критерии готовности

- [ ] Чистая установка на PHP 8.2/8.3, MariaDB 11.4, Redis 7.4 и без Redis.
- [ ] Upgrade с alpha-дампа: backup, миграции, проверка целостности.
- [ ] `/panel/`, классы, объекты, свойства, методы, сценарии, события,
  пользователи, настройки, ошибки, задания работают на новом ядре.
- [ ] Все базовые модули переписаны; `module.class.php`, `view_mode`, `pd`,
  `jTemplate`, `admin_router.php`, `nf.php` не используются.
- [ ] `lib/*.php` и `lib/core/` либо удалены, либо оставлены только как
  compatibility runtime для пользовательских сценариев.
- [ ] Публичный API v1 проходит контрактные тесты; v2 документирован.
- [ ] PHPUnit, PHPStan, Rector, PHP-CS-Fixer, smoke-тесты и `npm run build`
  зелёные в CI на PHP 8.2 и 8.3.
- [ ] Нет известных критических уязвимостей; `composer audit` чист.
- [ ] Документация MyDoMo описывает архитектуру, модули, обновление и откат.
- [ ] Есть метрики: время ответа панели, API, запись свойства, очередь;
  нет регрессии больше согласованного порога.
- [ ] Тег `2.0.0` и инструкция по обновлению; alpha остаётся доступной.

## 13. Открытые решения

1. **PHP 8.2+** — рекомендуем. Если 8.1 обязателен, стек откатывается к
   Symfony 6.4/Illuminate 10; это отдельное решение.
2. **Illuminate 12 components vs full Laravel** — рекомендуем components.
   Полный Laravel — только если spike покажет, что склейка дороже.
3. **Blade vs Twig vs JTemplate** — рекомендуем Blade для нового UI.
4. **Публичный API v1** — рекомендуем сохранить как compatibility island;
   v2 — новый контракт.
5. **Пользовательские сценарии/методы** — рекомендуем compatibility runtime,
   а не автоматическую миграцию.
6. **Модульные зависимости** — сначала core vendor; позже собственный
   `composer.json` модуля; изоляция — отдельно.
7. **Изоляция модулей** — вне первого прохода; модули доверенные.
8. **Схема БД** — сохранить имена таблиц; InnoDB/utf8mb4; без destructive
   изменений в первом релизе.
9. **Redis** — опционален; Predis fallback; без Redis — database/file.
10. **Очередь** — сохранить собственную долговечную; Laravel Queue/Symfony
    Messenger не брать без отдельного spike.
11. **UI-навигация** — `partLoad` заменить после переноса shell; fetch/HTMX
    решить на прототипе.
12. **Версия и окно поддержки** — MyDoMo 2.0; alpha — только критические
    исправления безопасности.

## 14. Ориентиры

Оценка очень грубая, для одного опытного разработчика:

- фундамент, CI, spike — 1–2 недели;
- HTTP, роутер, auth, Blade — 2–3 недели;
- БД/Eloquent/миграции — 3–4 недели;
- кэш, очередь, события — 2–3 недели;
- module SDK — 2–3 недели;
- базовые модули — 6–10 недель;
- интеграции и тяжёлые модули — 4–6 недель;
- удаление legacy, миграция, релиз — 2–4 недели.

Итого: ориентировочно 4–6 месяцев без параллельной работы; с параллельными
модулями можно сократить календарь, но не объём проверок. Оценки уточняются
после spike и первой волны модулей.

## Приложение A. Карта старых файлов

| Старое | Новое |
| --- | --- |
| `public/nf.php`, `lib/admin_router.php` | `src/Http/Kernel.php`, `src/Routing/*` |
| `public/admin.php`, `public/index.php` | `public/index.php` + kernel |
| `lib/module.class.php` | `src/Modules/ModuleManager.php`, providers |
| `lib/mysql.class.php`, `lib/mysqli.class.php` | `src/Database/*` |
| `lib/sqlFunctions.class.php` | репозитории + compatibility-фасады |
| `lib/objects.class.php` | `src/Core/Objects` |
| `lib/events.class.php`, `lib/hooks.inc.php` | `src/Events/*` |
| `lib/caching.class.php`, `lib/redis_helper.class.php` | `src/Cache/*` |
| `lib/parser.class.php`, `lib/jTemplate.class.php` | Blade |
| `lib/threads.php`, `cycle.php` | `src/Console`, workers |
| `lib/errors.class.php` | `src/Support/ErrorHandler`, Monolog |
| `lib/general.class.php`, `lib/common.class.php` | `src/Support/*` |
| `lib/terminals.class.php`, `lib/python.inc.php` | `src/Support/Processes` |
| `lib/xml.class.php`, `lib/xml_data.class.php` | `src/Support/Xml` |
| `lib/markdown.php` | CommonMark или `src/Support/Markdown` |
| `lib/knowledge_base.php` | module `knowledge_base` + service |
| `lib/dashboard_data.php` | `panel` service |
| `lib/error_page.php` | HTTP error response |
| `lib/perfmonitor.class.php` | profiler/timer |
| `templates/*.html` (JTemplate) | Blade + компоненты |
| `modules/<name>/<name>.class.php` | `modules/<name>/src/*` |

## Приложение B. Шаблон манифеста

```json
{
  "id": "keenetic",
  "name": "Keenetic",
  "version": "1.0.0",
  "requires": {
    "mydomo": "^2.0",
    "php": ">=8.2"
  },
  "provider": "MajorDoMo\\Modules\\Keenetic\\KeeneticServiceProvider",
  "routes": {
    "web": "routes/web.php",
    "api": "routes/api.php"
  },
  "migrations": "migrations",
  "views": "resources/views",
  "assets": "resources/assets",
  "docs": "docs",
  "menu": {
    "title": "Keenetic",
    "icon": "router",
    "category": "devices",
    "order": 40,
    "route": "keenetic.index"
  },
  "permissions": ["objects.read", "objects.write", "settings.own"]
}
```

## Приложение C. Шаблон контроллера

```php
<?php

namespace MajorDoMo\Modules\Keenetic\Http\Controllers;

use MajorDoMo\Http\Controller;
use MajorDoMo\Modules\Keenetic\KeeneticService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class RouterController extends Controller
{
    public function __construct(private KeeneticService $keenetic) {}

    public function index(Request $request): View
    {
        return view('keenetic::routers.index', [
            'routers' => $this->keenetic->routers(),
        ]);
    }
}
```

## Связанные документы

- [Исходная заметка по роутингу](routing.md)
- [Eloquent ORM](eloquent-orm.md)
- [Коллекции](collections.md)
- [Фасад Redis](redis-facade.md)
- [Фасад Cache](cache-facade.md)
- [Точки входа alpha](../agents/entrypoints.md)
- [Ядро alpha](../agents/core-runtime.md)
- [API alpha](../agents/api.md)
- [Конфигурация alpha](../agents/configuration.md)
- [Деплой alpha](../agents/deployment.md)
- [Правила раздела TODO](README.md)
