---
title: MyDoMo — архитектура ядра
audience: agent, user
category: core
tags: mydomo, архитектура, kernel, container, сервисы, boot
description: Текущая сборка приложения, HTTP-путь, граница ядровых страниц и SDK, данные и фоновые процессы.
order: 2
---

# Архитектура MyDoMo

## Основа приложения

MyDoMo использует компоненты Illuminate 12, Symfony Console, Blade/Yoyo,
Monolog и Carbon на PHP 8.2+. Это не стандартное Laravel-приложение:
не следует добавлять Artisan-команды, Eloquent-политики или Laravel-
конфигурацию, предполагая, что соответствующий runtime уже подключён.

```text
public/index.php
  bootstrap/app.php
    Foundation/Application
      Container + ConfigLoader + сервисы
      Http/Kernel
        Illuminate Router
          routes/web.php — ядровые страницы
          routes/api.php — API v1
          ModuleManager — маршруты включённых SDK-модулей
```

Жизненный цикл — `src/Foundation/Application.php`; регистрации зависимостей
сгруппированы в `src/Foundation/Providers` и перечислены в
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
проверяет режим обслуживания и передаёт запрос Router. Результат проходит
через общие security headers и получает `X-Request-Id`.

404 и 405 обрабатываются отдельно, 405 сохраняет `Allow`. Необработанное
исключение логируется, передаётся ErrorRepository и превращается в 500.
JSON выбирается по `Request::expectsJson()`, а не просто по началу URL.
Браузер получает автономную HTML-страницу `resources/views/errors/page.blade.php`:
код ответа, короткое объяснение, путь или request id, маскот и действия.
Она не читает БД и не собирает меню, поэтому открывается и при сбое базы;
если Blade недоступен, kernel отдаёт прежний текст с request id.
Stack trace не отправляется клиенту; текст исключения появляется только
в debug — в раскрывающемся блоке страницы. Собственные ответы контроллеров
могут иметь иной JSON-конверт.

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
| Страница ядра | `src/Http/Controllers/Core`, `resources/views/pages` | Репозитории/сервисы ядра | Часть приложения |
| API-webhook | `src/Api/Webhooks` | `api_webhooks` через репозиторий | Часть приложения |
| SDK-модуль | `modules/<id>` или `modules/core/<id>` | Свой ModuleDb и ModuleBus | Манифест и lifecycle |

`src/Panel/CoreSections.php` задаёт карту ядровых страниц и их меню.
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
`public/assets/admin/mydomo.js` и `mydomo.css`.

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
- `mydomo-agent` принимает ограниченные команды systemd через Unix socket.

Они не запускаются автоматически из HTTP-запроса. Production-состояние
фоновых задач предполагает Redis, сами задания и таймеры хранятся в БД.
Отдельный процесс событий/outbox пока запланирован. Системные unit-файлы,
ограничения fork/lock и способ установки — [runtime.md](runtime.md).

## Границы готовности

Новый SDK не совместим с классами модулей alpha; alpha-runtime удалён из
ветки и остаётся в `alpha` как источник истории. Полный API v2, установщик,
автоматизированное обновление, Docker-стенд и sandbox пользовательского PHP
не реализованы. Для точных исключений и расхождений см.
[limitations.md](limitations.md).
