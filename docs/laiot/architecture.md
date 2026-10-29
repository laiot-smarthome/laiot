---
title: LaIOT — архитектура ядра
audience: agent, user
category: core
tags: laiot, архитектура, kernel, container, сервисы, boot
description: Текущая сборка приложения, HTTP-путь, граница ядровых страниц и SDK, данные и фоновые процессы.
order: 2
---

# Архитектура LaIOT

## Основа приложения

LaIOT использует компоненты Illuminate 12, Symfony Console, Blade/Yoyo,
Monolog и Carbon на PHP 8.2+. Это не стандартное Laravel-приложение:
не следует добавлять Artisan-команды, Eloquent-политики или Laravel-
конфигурацию, предполагая, что соответствующий runtime уже подключён.

## Структура кода

Корень повторяет привычную раскладку Laravel, но остаётся собственным
приложением: `app/` — код ядра, `modules/` — SDK-модули, `routes/` и
`resources/views` — HTTP-вход и шаблоны, `config/` — конфигурация.

```text
app/
├── Console/Commands/     команды php bin/laiot
├── Database/             подключение, мигратор, базовые репозитории
├── Foundation/           Application, Config, ServiceProvider
├── Http/                 Kernel, сессии, ошибки, PanelRenderer
│   ├── Controllers/      контроллеры страниц; Core/ — разделы админки
│   └── Middleware/       auth, CSRF, права, лимиты
├── Models/               Eloquent-модели: Model, Setting
├── Providers/            сервис-провайдеры из config/providers.php
├── Services/             прикладные сервисы и репозитории по доменам
│   ├── Objects/, Properties/, Events/, History/
│   ├── Modules/, Queue/, Runtime/, Worker/
│   ├── Auth/, Settings/, Locations/, Notifications/
│   └── ...               остальные домены
├── Support/              helpers, AppContext, Clock
├── View/                 Blade-адаптер
└── Yoyo/                 серверные компоненты интерактива
```

Правило размещения: HTTP-слой принимает запрос и формирует ответ,
контроллер не содержит доменной логики; состояние и операции домена —
в `app/Services/<Домен>`, доступ к таблицам — в репозиториях того же
домена, сущности Eloquent — в `app/Models`. Интеграции (MQTT, Zigbee,
внешние расширения) остаются в `modules/` и не растворяются в `app/`.
Публичные функции-обёртки — `app/Support/helpers`, их справочник —
[functions.md](functions.md).

```text
public/index.php
  bootstrap/app.php
    app/Foundation/Application
      Container + ConfigLoader + сервисы
      app/Http/Kernel
        Illuminate Router
          routes/web.php — ядровые страницы
          routes/api.php — API v1
          ModuleManager — маршруты включённых SDK-модулей
```

Жизненный цикл — `app/Foundation/Application.php`; регистрации зависимостей
сгруппированы в `app/Providers` и перечислены в
`config/providers.php`. Контракт расширения — [providers.md](providers.md).
Контроллеры получают Request и сервисы через контейнер; `Application::handle`
привязывает текущий Illuminate Request перед передачей kernel.

## Boot и конфигурация

`bootstrap/app.php` подключает autoload, запускает общий Config и передаёт
разрешённые значения в Application. Конструктор Application объединяет
`config/*.php` с явно переданным массивом; тесты не используют рабочий
bootstrap. Порядок значений и известные расхождения —
[configuration.md](configuration.md).

При `boot()` сначала выполняется register всех сервис-провайдеров, затем
их boot. Регистрации задают ленивые singleton; инфраструктурный boot
готовит каталоги, Clock и helpers, View — Yoyo, HTTP — маршруты ядра,
Module — SDK. Runtime-провайдер не запускает фоновые процессы.
В конце Application обновляет справочники имён и действий маршрутов.
Сессия, kernel и прикладные сервисы создаются при разрешении контейнером,
а публичные getters продолжают возвращать их общие экземпляры.

DatabaseManager открывает соединение лениво, но весь boot не является
гарантированно свободным от БД: при настроенном соединении SDK читает
реестр сторонних модулей. При отсутствии конфигурации этот шаг пропускается.
Подготовка схемы выполняется миграциями, а не установкой всей БД в boot;
некоторые отдельные сервисы имеют низкоуровневое создание своих таблиц.

## HTTP-запрос

Kernel принимает или генерирует request ID, устанавливает контекст логов,
проверяет завершённость установки и режим обслуживания, затем передаёт
запрос Router. До установки браузерные страницы уводятся на `/install`, а
API получает JSON `503 not_installed`; health и статика остаются доступны.
Результат проходит через общие security headers и получает `X-Request-Id`.

404 и 405 обрабатываются отдельно, 405 сохраняет `Allow`. Необработанное
исключение логируется, передаётся ErrorRepository и превращается в 500.
JSON выбирается по `Request::expectsJson()`, а не просто по началу URL.
Браузер получает автономную HTML-страницу `resources/views/errors/page.blade.php`:
код ответа, короткое объяснение, путь или request id, маскот и действия.
Она не читает БД и не собирает меню, поэтому открывается и при сбое базы.
В debug исключение рендерится через автономный `Http/ExceptionPage` и
`resources/views/errors/exception.php`: тип, сообщение, файл/строка,
исходник, стек, предыдущие исключения и копирование отчёта для ИИ.
Аргументы вызовов и данные запроса не собираются. Без debug технические
детали скрыты. При недоступном Blade ошибка 500 использует тот же PHP-шаблон
в обычном режиме; для остальных статусов резервом остаётся текст.
`public/index.php` устанавливает `EarlyErrorHandler` до Composer и bootstrap:
он перехватывает необработанные исключения и фатальные ошибки PHP через
shutdown, очищает незавершённый вывод, выставляет 500 и request id. Для
ранних ошибок используется журнал PHP, без обращения к БД или DI.
До загрузки Config подробности скрыты; ограничения — [logging.md](logging.md).
Собственные ответы контроллеров
могут иметь иной JSON-конверт. По тому же правилу работает `/install`:
пока `DB_NAME` не задан, соединение не открывается и мастер показывает
требования до установки.

Middleware:

| Алиас | Назначение |
| --- | --- |
| `request-id` | Дополнительный контекст/маркер запроса |
| `auth` | Действующий администратор и совпадающий stamp |
| `csrf` | Токен изменяющего браузерного запроса |
| `can:<permission>` | Разрешение через Authorization |
| `api.auth` | Bearer/X-API-Key из таблицы токенов |
| `throttle.login` | Лимит попыток входа по IP |

В режиме обслуживания разрешены вход, выход, health и маршруты управления
самим обслуживанием. У последних сохраняются auth, право и CSRF.
Подробности — [authentication.md](authentication.md), [api.md](api.md).

## Ядровые разделы и модули

| Часть | Где код | Доступ к данным | Жизненный цикл |
| --- | --- | --- | --- |
| Страница ядра | `app/Http/Controllers/Core`, `resources/views/pages` | Репозитории/сервисы ядра | Часть приложения |
| API-webhook | `app/Http/Controllers/WebhookController.php`, `app/Services/Webhooks` | `api_webhooks` через репозиторий | Часть приложения |
| SDK-модуль | `modules/<id>` или `modules/core/<id>` | Свой ModuleDb и ModuleBus | Манифест и lifecycle |

`app/Services/Panel/CoreSections.php` задаёт карту ядровых страниц и их меню.
`ModuleManager` добавляет меню включённых интеграций. Удалённые
`modules/core/objects`, `users`, `api`, `knowledge_base` и подобные не
являются текущей архитектурой и не должны восстанавливаться как обёртки.

SDK регистрирует `/module/<id>` с auth и контекстом ModuleDb/ModuleBus.
Тип core у SDK-модуля означает встроенную неотключаемую интеграцию;
он не делает любую страницу панели модулем. Подробнее —
[core-sections.md](core-sections.md), [modules.md](modules.md), [sdk.md](sdk.md).

## Сервисы и данные

| Слой | Основные классы |
| --- | --- |
| База | DatabaseManager, Model, Migrator, SettingsRepository |
| Доступ | UserRepository, AdminAuthenticator, Authorization, RoleRepository, ApiTokenRepository |
| Дом | ClassRepository, ObjectRepository, PropertyRepository, MethodRepository |
| Значения | ValueRepository, PropertyValueService, HistoryRepository, HistoryQuery |
| Пользовательский код | MethodRunner, MethodContext, ScriptRunner, CodeRunner, TimeoutRepository |
| События | PropertyValueService, PropertyEventContext, PropertyEventHandler, PropertyEventRepository, ModuleBus |
| Задания | Queue/DatabaseQueue, QueueWorker, JobRegistry |
| Панель | CoreSections, PanelRenderer, PanelPage, PanelSearch, LinkedObjectService |
| Переименование | CodeReferenceRewriter, NameReferenceUpdater |
| Диагностика | LoggerFactory, RequestContext, ErrorRepository, ActionLogger |
| Сообщения | NotificationService |
| Документы | KnowledgeBase, MarkdownRenderer |

Классы образуют дерево (`parent_id`), а свойства/методы ищутся по цепочке
наследования. Системные имена латиницей и уникальны: свойство/метод — во всей
ветке класса, поэтому одноимённое переопределение не создаётся; поиск по
цепочке остаётся для чтения legacy-строк. Локация принадлежит объекту
(`objects.location_id`), не классу. Запись свойства проходит
PropertyValueService: проверка объекта/свойства, сохранение значения и
постановка задания `property.changed` в очередь `events` одной транзакцией.
OnChange, историю и доставку модулям выполняет отдельный процесс
`core-worker events` (`PropertyEventHandler`).
Поэтому запись датчика не ждёт пользовательского PHP, а тысячи изменений
обрабатываются в своём процессе.

Ошибка обработчика не отменяет уже сохранённое значение. ModuleBus — другая
шина: retained-данные интеграций с TTL и подписки текущего процесса.
Нельзя описывать эти механизмы как один общий event dispatcher с фоновой
доставкой. См. [events.md](events.md) и [queue.md](queue.md).

Схема lowercase, timestamps и soft delete описаны в
[database.md](database.md). Реестр модулей не управляет ядровыми таблицами.

## Представления

PanelRenderer собирает `layouts.alpha` и PanelPage. Название alpha
сохраняет происхождение визуала. Представления — Blade, интерактивность —
Alpine, отдельные демонстрационные компоненты — Yoyo. Компоненты `x-md.*`
живут в `resources/views/components/md`, JS/CSS — в `resources`, бандл —
`public/assets/admin/laiot.js` и `laiot.css`.

jQuery, старые admin.js/JTemplate/MDJUI PHP не являются runtime нового UI.
BladeRenderer остаётся автономным адаптером рендера и используется
проверками; его наличие не означает подключение старой оболочки.

## Фоновые процессы

- `worker <id>` выполняет tasks SDK-модуля и очередь доставки `module.<id>`.
- `core-worker queue` обслуживает очередь default.
- `core-worker events` выполняет OnChange, историю и доставку свойства;
  `core-worker events.<n>` — одну полосу очереди, для параллельной работы.
- `core-worker timeouts` выполняет созревший пользовательский код.
- `core-worker system-events` создаёт системные события времени для модулей
  и привязанных сценариев.
- `laiot-agent` принимает ограниченные команды systemd через Unix socket.

Они не запускаются автоматически из HTTP-запроса. Production-состояние
фоновых задач предполагает Redis, сами задания и таймеры хранятся в БД.
Отдельный процесс событий/outbox пока запланирован. Системные unit-файлы,
ограничения fork/lock и способ установки — [runtime.md](runtime.md).

## Границы готовности

Новый SDK не совместим с классами модулей alpha; alpha-runtime удалён из
ветки и остаётся в `alpha` как источник истории. Полный API v2,
production-Docker и sandbox пользовательского PHP не реализованы;
установка есть в виде страницы `/install` и команды `install`,
[обновление и восстановление](updates.md) — отдельная служба с файловым
журналом и снимками MySQL. Для точных
исключений и расхождений см. [limitations.md](limitations.md).
