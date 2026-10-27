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

## Актуальное прочтение плана, 2026-10-26

После переноса ядровых страниц действуют следующие уточнения. Детальные
ранние этапы и варианты ниже сохраняются как история проектирования;
они не должны отменять уже изменённую архитектуру.

- CRUD, журналы, API и настройки — ядровые разделы `src` + Blade,
  не пакеты `modules/core`. MQTT/Zigbee и сторонние интеграции — SDK-модули.
- Текущий CLI — `php bin/mydomo`, классы `src/Console`. Старые примеры
  `php majordomo`, `bin/majordomo` и routing_smoke относятся к ранним этапам.
- API v1 нового ядра имеет ограниченный набор маршрутов и разные конверты.
  Полная совместимость alpha и API v2/OpenAPI пока не подтверждены.
- CodeGuard, legacy:scan, объектный `$this` и outbox для OnChange —
  целевые требования, не готовые механизмы защиты/исполнения.
- Состояние worker предполагает Redis; его обязательность пока не
  проверяется конструктором. Array используется для изолированных тестов.
- Docker — локальная отладка. Старый docs/deploy не является завершённой
  production-инструкцией MyDoMo; установка и обновление ещё требуют работы.

Проверенные контракты: [docs/mydomo](../mydomo/README.md),
а исключения — [limitations.md](../mydomo/limitations.md).

## 0. Текущий прогресс

- [x] Изоляция модульных очередей (2026-10-27): доставка изменений свойства
  уходит заданием в `module.<id>` и выполняется `worker <id>`; медленный или
  упавший подписчик не задерживает events и другие модули. Проверено
  `PropertyDeliveryTest` (11 тестов), `ModuleSdkTest`; документы —
  [queue.md](../mydomo/queue.md), [runtime.md](../mydomo/runtime.md),
  [events.md](../mydomo/events.md).
- [x] SDK перед MQTT/Zigbee (2026-10-27): `permissions` манифеста
  регистрируются в таблице с владельцем `module_id`, ассеты модуля
  публикуются в `public/assets/modules/<id>`, `module:remove --purge`
  удаляет таблицы и SQLite-данные, `module:check` ищет legacy-вызовы.
  Проверено `ModulePermissionsTest`, `ModuleAssetsTest`, `ModuleCheckerTest`,
  `ModuleSdkTest`; документы — [sdk.md](../mydomo/sdk.md),
  [modules.md](../mydomo/modules.md), [console.md](../mydomo/console.md).
- [x] Логи и файлы ошибок (2026-10-27): каналы `app/http/security/queue/events`
  пишут отдельные файлы с суточной ротацией (`logging.directory/max_files`);
  каждая запись `system_errors` получает свой файл с контекстом и trace
  (`log_file`), удаление записи или группы удаляет файл. Проверено
  `LoggingTest` (3), `ErrorFileWriterTest` (2), `ErrorRepositoryTest` (2);
  документы — [logging.md](../mydomo/logging.md),
  [configuration.md](../mydomo/configuration.md). Остаток: показ файла в
  админке и retention таблицы.
- [x] Рантайм пользовательского кода (2026-10-27): `MethodContext` даёт методу
  `$this` с `get/set/history/call/notify` и короткими именами свойств;
  `HistoryQuery` и обёртки `history()`, `getHistoryRange`,
  `getHistoryAvg/Min/Max/Sum/Count` считают период и агрегаты одним SQL по
  индексу истории. Сценарии остаются без объекта (`$params` и обёртки).
  Проверено `MethodContextTest` (4 теста) и `HistoryQueryTest` (5 тестов);
  документы — [functions.md](../mydomo/functions.md),
  [architecture.md](../mydomo/architecture.md),
  [limitations.md](../mydomo/limitations.md).
- [x] Асинхронный конвейер свойств (2026-10-27): запись значения и задание
  `property.changed` в `queue_jobs` — одна транзакция; `core-worker events`
  выполняет OnChange → историю → доставку модулям. Цепочка, родитель журнала
  и модуль-автор едут в payload (`PropertyChangedJob`,
  `PropertyEventContext`, `PropertyEventHandler`). Тесты Feature дренят
  очередь через `DrainsEvents`; события разложены по полосам
  `events.<n>` (свойство всегда в одной полосе), несколько процессов
  `core-worker events.<n>` обрабатывают их параллельно. Документы —
  [events.md](../mydomo/events.md), [queue.md](../mydomo/queue.md),
  [runtime.md](../mydomo/runtime.md). Остаток блока: метрики очередей.
- [x] Доставка изменений свойства модулям (2026-10-27): таблица
  `property_subscriptions` вместо `pvalues.LINKED_MODULES`, интерфейс
  `PropertySubscriber` + реестр, шаг журнала `module.delivery` со статусами
  ok/error/missing; эхо гасится контекстом обработчика, без alpha-параметра
  `$noLinked`. Подписку выбирает пользователь, а не манифест; страница
  выбора появится с MQTT/Zigbee. Проверено `PropertyDeliveryTest` (9 тестов),
  `CoreEventsTest`, `PropertyValueIntegrityTest`; документы —
  [events.md](../mydomo/events.md), [sdk.md](../mydomo/sdk.md),
  [modules.md](../mydomo/modules.md), [database.md](../mydomo/database.md).
- [x] Модель дома сведена в единый раздел «Классы» (2026-10-27): объекты,
  свойства, методы, значения и история больше не отдельные пункты меню.
  Класс получил `parent_id` и неограниченную глубину подклассов; свойства и
  методы наследуются по цепочке, объектные члены (`object_id`) перекрывают
  классовые. Значения по-прежнему хранятся только у объекта. Проверено
  `ClassHierarchyTest`, обновлёнными `AdminSectionsTest`, `CoreEventsTest`,
  `PropertyValueIntegrityTest` и полным `composer check`; документы —
  [admin-ui.md](../mydomo/admin-ui.md), [core-sections.md](../mydomo/core-sections.md),
  [database.md](../mydomo/database.md), [events.md](../mydomo/events.md).
- [x] Сборка Application разделена на шесть сервис-провайдеров (2026-10-27):
  register всех зависимостей → boot подсистем; список config/providers.php,
  ленивые singleton, общий kernel-контекст и расширение своими провайдерами.
  Регрессионный контракт — ServiceProvidersTest; описание —
  [providers.md](../mydomo/providers.md).
- [x] Аудит документации MyDoMo: обновлены архитектура, разделение ядра и
  SDK, функции, runtime, данные и навигация; добавлены руководства по
  конфигурации, доступу, API, кэшу, очередям, CLI, диагностике и ограничениям.
  Менялась только документация; результаты —
  [documentation-audit.md](../mydomo/documentation-audit.md).

- [x] Веб-админка перестроена как новая реализация alpha-разделов (2026-10-26):
  оболочка и меню по задачам, обзор на реальных данных (`DashboardStats`),
  объекты с фильтрами и вкладками (свойства/методы/история/журнал), дерево
  классов, свойства, методы, локации, значения, история, скрипты, события,
  очередь, база знаний, пользователи, роли, настройки, журнал, ошибки, модули,
  API/webhook, токены, оповещения, обслуживание и профиль. Все ядровые ссылки
  переведены с `/module/*` на чистые URL; добавлены компоненты
  `x-md.page-hero/table/tabs/icon-button`, утилиты CSS и `md-prose`.
  Проверено `AdminSectionsTest` (5 тестов), профильные тесты разделов,
  PHPStan и php-cs-fixer; визуально — 1500 px и 390 px, светлая и тёмная тема.
  Детали и список неперенесённого — [admin-ui.md](../mydomo/admin-ui.md).
- [x] Функциональные пробелы alpha закрыты в админке (2026-10-26): управление
  жизненным циклом SDK-модулей из UI (установка/включение/отключение/удаление
  через `ModuleInstaller`), категории сценариев и JSON-импорт/экспорт,
  разделы настроек по префиксу, группы одинаковых ошибок с удалением группы,
  фильтры по объекту в значениях и истории, общий диалог подтверждения
  `mdConfirmDialog` вместо `window.confirm()`. Проверено `CoreScriptsTest`,
  `CoreSettingsTest`, `CoreSystemErrorsTest`, `ModulesPageTest`,
  `AdminSectionsTest`.
- [x] Редактор кода перенесён на CodeMirror 6 (2026-10-26): отдельный
  lazy-chunk `mydomo-codeeditor.js/css` (PHP-подсветка, поиск, отступы,
  oneDark в тёмной теме) грузится только при наличии
  `textarea[data-code-editor]`; без JS поле работает как обычная textarea.
  Проверено в браузере в светлой и тёмной теме, `AdminSectionsTest`.
- [x] История свойства перенесена на Yoyo-компонент (2026-10-27): отдельный
  JSON-эндпоинт `/api/v1/panel/history` удалён, `property-history` сам
  перерисовывает drawer и период; данные считает `PropertyHistoryService`,
  график — ленивый чанк ApexCharts, монтируется после `htmx:afterSwap`.
  Кнопка есть в «Значениях» и во вкладке «Свойства» карточки объекта.
  Проверено `PropertyHistoryTest` (3 теста) и в браузере.
- [x] Yoyo-компоненты подхватываются автоматически (2026-10-27): убраны
  ручные `Yoyo::registerComponent()`, имя `kebab-case` разрешается в
  `MajorDoMo\Yoyo\StudlyCase` по namespace; правило «интерактив — Yoyo,
  не JSON-эндпоинт» закреплено в `AGENTS.md`, `docs/mydomo/AGENTS.md`
  и [admin-ui.md](../mydomo/admin-ui.md). Журнал событий переведён на
  Yoyo-таблицу `events-table`: поиск, тип и статус перерисовывают таблицу
  через `/yoyo`. Проверено `YoyoTest`, `CoreEventsTest` (9 тестов).
- [x] Аудит записи значений (2026-10-26): `setValue()` отклоняет свойство
  чужого класса; обновление/восстановление сохраняет `created_at`.
  Проверено 19 тестов / 131 assertion на SQLite; детали —
  [core-audit.md](../mydomo/core-audit.md).
- [x] Завершены отложенные исправления HTTP/auth и очереди (2026-10-26):
  обязательный `stamp`, отзыв сессий, смена пароля из профиля, сброс лимита,
  выключение обслуживания, безопасный debug, повторная резервация задания.
  Адресный набор: 36 тестов / 158 assertions без PHP-warning; общий прогон
  требует повторения после параллельного переноса core-страниц и API.
- [x] Ветка `MyDoMo` создана от `alpha` (`0bc4c355`), `alpha` не тронута.
- [x] Мастер-план и границы ветки зафиксированы в документации.
- [x] Composer-стек разрешён dry-run на PHP 8.2; `roave/security-advisories`
  исключён из основного набора.
- [x] Spike 0 в ветке `MyDoMo`: контейнер, Illuminate Routing, Blade,
  Eloquent на SQLite, Monolog, middleware, 404/405/500, PHPUnit.
- [x] `composer check`, PHPStan, PHP-CS-Fixer, Rector dry-run и ручные HTTP-
  проверки проходят локально.
- [x] Добавлен CI-workflow; первый зелёный прогон на GitHub ещё нужен.
- [x] Этап 1: `Config` + PDO/Eloquent, health-check, настройки через
  репозиторий, базовые `Controller`/`Repository` и трейт ответов.
- [x] MariaDB-проверка на одноразовом контейнере: схема, insert, select,
  drop прошли.
- [x] Yoyo 0.15.1 + Blade подключён через собственный адаптер; компонент
  рендерится, POST `/yoyo` обновляет HTML; htmx/yoyo.js лежат локально.
- [x] Новый `public/index.php` заменил старый вход; spike-файлы удалены.
- [x] Вход администратора: таблица `users`, `Passwords`, PDO, отдельная
  сессия `mydomo`, CSRF, `/login` и `/logout`.
- [x] `/yoyo` закрыт auth + CSRF; Yoyo-страница передаёт токен в htmx.
- [x] Docker-образ пересобран после Stage 2: `/`, `/health`, `/login`,
  вход `admin/majordomo` и `/yoyo-demo` проверены.
- [x] ModuleManager проверен на прототипе; `modules/welcome` удалён, каталог
  `modules/core/` зарезервирован под core-модули.
- [x] Модульная система утверждена: два типа `core`/`third-party`,
  core-модули в `modules/core`, нельзя удалять/отключать; спецификация —
  [modules.md](../mydomo/modules.md).
- [x] `ModuleDb`: MySQL/SQLite, префикс `module_<id>_`, allowlist таблиц,
  запрет raw/join; 4 теста.
- [x] `ModuleBus`: retained events с TTL, namespace и wildcard-подписки;
  4 теста.
- [x] `CacheStore` + фабрика: array/file/database/redis, TTL, `remember`,
  `has`, `forget`, типы; Redis `clear` через SCAN; 5 тестов.
- [x] Логи: `LoggerFactory` с каналами `app`/`http`/`security` и
  `request_id` в `extra`; тест.
- [x] Новый CLI `bin/mydomo` на Symfony Console: `about`, `health`,
  `cache:clear`, `module:list`, `route:list`, `queue:stats`, `queue:work`,
  `queue:failed`, `queue:retry`, `db:migrate`, `db:status`; 9 CLI-тестов.
- [x] `Queue` + `DatabaseQueue`: отложенные задания, резервирование,
  повторы и dead-letter по попыткам; 4 теста.
- [x] `JobRegistry` + `QueueWorker` + `queue:work`: именованные обработчики,
  ack/fail, ошибки в лог; 2 теста worker и CLI-тест.
- [x] Dead-letter: `queue:failed`, `queue:retry`, `forget`; список/повтор/
  удаление упавших заданий.
- [x] `ErrorResponse`: 404/405/500 в JSON или тексте, request_id, без trace.
- [x] `ConfigLoader`: `config/cache.php`, `config/queue.php`,
  `config/logging.php`; 2 теста.
- [x] `Migrator` + `database/migrations/`, `db:migrate`, `db:status`;
  1 unit-тест и CLI-тест.
- [x] Базовая `Model` (`ID`, без timestamps) и `DatabaseManager::transaction()`;
  2 теста.
- [x] Redis-кэш (`CacheFactory`) через Predis и SCAN-clear; live Redis в
  тестах не поднимался.
- [x] Новые таблицы: lowercase snake_case, `created_at/updated_at/deleted_at`;
  Carbon/Clock и `APP_TIMEZONE`; 1 тест.
- [x] Typed jobs: `Job` + `Queue::pushJob()`; 2 теста.
- [x] API v1: `health`, `about`; JSON + request_id; 2 теста.
- [x] AuthZ: `Authorization`, middleware `can:`, таблицы ролей и
  `RoleRepository`; 5 тестов.
- **UI:** новый дизайн админки не делаем. Внешний вид alpha сохраняем,
  представления переносим на Blade/Yoyo; используем `admin.css`,
  `mydomo.css`/`mydomo.js`, `x-md.*` и Alpine, без нового layout/CSS.
- [x] Первый core-модуль `shoutbox` перенесён полностью: `modules/core`,
  `ModuleDb`, Blade, alpha-layout; старый код удалён.
- [x] `api`: входящие webhook на `module_api_webhooks`, секрет SHA-256,
  методы, сценарий/PHP, `/api/v1/webhooks/{secret}`; старый `modules/api`
  удалён; 4 теста, документация.
- [x] `blockly_code` заменён `x-md.code` (Alpine, нумерация строк) в формах
  скриптов/методов; старый модуль и шаблон удалены.
- [x] linked object вынесен в ядро: `LinkedObjectService`,
  `/api/v1/panel/linked-object`, `x-md.linked-object` на Alpine; старый
  `modules/linkedobject` удалён; 3 теста.
- [x] SDK модулей: `ModuleManifest`, `ModuleRegistry`/`ModuleMigrations`,
  `ModuleInstaller`, `ModuleContext`/`Module`, install/enable/disable/remove,
  CLI `module:*`, пример `modules/example_sdk`; 3 теста.
- [x] Фоновый runtime: `tasks` в манифесте, `worker <module>` (один процесс
  на модуль, Redis-lock/state, fork/timeout), systemd-агент и шаблон
  `mydomo-worker@.service`; 4 + 4 теста.
- [x] Ядровые разделы вынесены из `modules/core`: `CoreSections`, core
  controllers/views, URL без `/module`; `modules/core` пуст.
- [x] API/webhooks и Shoutbox/оповещения перенесены в ядро; старые
  `modules/core/api` и `modules/core/shoutbox` удалены; таблицы
  `api_webhooks` и `shared_events` — ядровые.
- [x] Blade-компоненты `x-md.button/icon/badge/card/section-heading/alert/
  avatar/empty-state/kpi/progress/status/field/input/textarea/select/
  checkbox/switch/disclosure/drawer/modal/skeleton/activity-feed/hint` и
  секция «Blade/Yoyo» в alpha UI Kit; `BladeRenderer` — временный мост.
- [ ] Core-модули: интегрировать страницу в alpha-оболочку, затем `ui_kit`
  и `shoutbox`.
- [ ] Ядро параллельно: кэш, очереди/события, логирование, CLI и панель.

### 0.1. Как ведём дорожную карту

- Обновляем этот файл в том же коммите, что и код: чекбоксы, факты, следующий
  шаг, известные ограничения.
- В конце каждого этапа фиксируем: что сделано, что проверено, что сломано,
  какой следующий шаг. Если агент прервался, следующий читает раздел 0 и
  продолжает с этого места.
- Не оставляем ложных «сделано»: если проверка была только ручной — так и
  пишем; если Docker не пересобран — отмечаем.
- Удалённый старый код фиксируем в журнале раздела 3.6, чтобы было видно,
  что уже заменено, а что ещё нет.
- Открытые вопросы и решения владельца держим в разделе 13, а не в переписке.

### 0.2. Стратегия: сначала ядро

Решение владельца 2026-10-26: **сначала полностью закрываем ядро**, затем
берём модули по одному и переносим целиком. Исключение — пилот `shoutbox`:
он уже прошёл вертикальный срез и подтвердил контракт ядра.

Core-gate:

1. [x] HTTP, ошибки, middleware, security headers — базово закрыто.
2. [x] Config + `.env`, типизированные значения.
3. [x] БД: PDO/Capsule, `Model`, `Migrator`, миграции, транзакции.
4. [x] Кэш: Redis, `clearPrefix`, CLI, health (live Redis не проверялся).
5. [~] Очередь: typed jobs, worker, failed/retry/pending; scheduler-совместимость
   ещё нет.
6. [x] Логи: каналы, request_id, уровни.
7. [x] Auth/роли/права: таблицы, `can:`, `/roles`, API-токены.
8. [x] Alpha-layout: `layouts.alpha` + `PanelRenderer` + меню; поиск в шапке,
   drawer и центр уведомлений готовы (`/api/v1/panel/search`,
   `/api/v1/panel/notifications`). Старая панель `/panel/`, `admin.php`,
   `nf.php` и alpha-бандл удалены; Yoyo-обёртки UI Kit — отдельный этап.
9. [x] API-слой и health: v1 health/about/modules/me + токены.
10. [x] Тесты, docs, Docker-инструкция.
11. [x] Цепочка события свойства: единая запись, история, `property_events`,
    OnChange-метод и защита от циклов; модуль `events` перенесён.

Остаток: остальные core-модули, Yoyo-обёртки UI Kit, install/update,
API v2 и live Redis. Обёртки `gg`/`sg`/`getObject`/`getHistory`/`callMethod`/
`setTimeOut`/`clearTimeOut` уже работают.

После gate переносится один законченный раздел/интеграция за раз.
Для страницы ядра — контроллер/сервис в src, Blade и маршрут ядра;
для интеграции — SDK-пакет с ModuleDb и lifecycle. Старый код удаляется,
результат проверяется до следующего переноса.

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

### 3.4. Структура проекта и точки входа

Целевое дерево:

```text
project/
├── bin/majordomo              единственная CLI-точка входа
├── bootstrap/app.php          сборка приложения, не публикуется
├── config/                    app, database, cache, queue, logging, modules
├── database/
│   ├── migrations/
│   └── schema/
├── modules/<id>/              код модулей, не публикуется
├── public/                    document root, только web
│   ├── index.php              единственный HTTP-front controller
│   ├── install.php            мастер первого запуска (исключение: БД может быть пуста)
│   ├── assets/                собранная статика
│   └── media/                 опубликованные пользовательские файлы
├── resources/
│   ├── views/                 Blade
│   ├── css/
│   └── js/
├── routes/                    web.php, api.php, console.php
├── src/                       ядро и приложение
│   ├── Core/
│   ├── Database/
│   ├── Http/
│   ├── Modules/
│   ├── Routing/
│   └── Support/
├── storage/                   cache, logs, sessions, module data
├── tests/                     Unit, Feature, Integration
├── tools/                     dev-инструменты, не публикуется
├── docs/                      документация, не публикуется напрямую
├── composer.json / composer.lock
├── package.json / package-lock.json
├── phpunit.xml / phpstan.neon / rector.php / .php-cs-fixer.dist.php
├── vite*.config.js
├── README.md / AGENTS.md / LICENSE / .env.example / .gitignore
└── docker/                    только локальная отладка
```

Правила:

- Web-точка входа только в `public/`. Никаких PHP-файлов в корне, `lib/`,
  `modules/`, `scripts/`, `tools/` и `cms/`, доступных как приложение.
- `public/admin.php`, `public/nf.php`, `public/api.php`, `public/404.php` и
  `public/spike.php` удаляются. API, админка, сайт, 404 и health идут через
  `public/index.php`.
- `public/install.php` — единственное исключение: мастер обязан работать без
  БД и без общего boot.
- Nginx: `root` строго на `public/`; никаких `root`-override на `modules/`,
  `cms/` и `docs/`. Статика модулей публикуется в `public/assets/modules/`;
  пользовательские медиа — в `public/media/`; документация читается
  контроллером базы знаний.
- `cms/` и `var/` — legacy-хранилища alpha. Новое — `storage/`; перенос
  данных и медиа делается миграцией, а не симлинками наружу.
- CLI — `bin/majordomo`; `deploy.sh` и `dev_server.php` переезжают в `bin/`
  и `tools/`; `php majordomo` остаётся совместимым алиасом на время alpha.
- В корне остаются только манифесты, конфиги инструментов, README/AGENTS,
  лицензия и `docker/`. Это правило проверяется тестом структуры.

### 3.5. Стиль кода: сахар, трейты, наследование

Пользователь просит больше сахара, трейтов и наследования. Применяем
осознанно, чтобы не получить «магию», которую не видит PHPStan.

- Базовые классы: `Http\Controller`, `Database\Repository`, `Core\Service`,
  `Database\Model`, `Database\Migration`, `Console\Command`.
- Трейты для повторяющегося поведения без состояния:
  `Http\Concerns\CreatesResponses`, `Support\Concerns\HasLogger`,
  `Support\Concerns\InteractsWithTime`, `Support\Concerns\HasUuid`.
  Трейт не прячет обязательные зависимости: соединение и логгер передаются
  через конструктор, а не берутся из глобального состояния.
- Сахар: `$this->json()/html()/text()`, именованные конструкторы,
  scope-методы моделей, `collect()`, `route()`, `Setting::value()`,
  `Response`-хелперы. Глобальные функции не добавляем, кроме совместимых
  обёрток пользовательского кода.
- Наследование — для общей реализации (контроллеры, репозитории, миграции,
  команды). Композиция — для зависимостей. Если базовый класс начинает
  знать про конкретный модуль, это ошибка.
- Любая магия (`__call`, `__get`, динамические фасады) документируется
  `@method`/`@property` и покрывается тестом; иначе PHPStan и IDE слепнут.
- Контроллеры и сервисы по возможности `readonly`; изменяемое состояние —
  только у моделей, сессий и долгоживущих воркеров.

### 3.6. Удаление старого кода

Старый код не храним «на всякий случай»: он есть в `alpha` и в истории Git.
После каждого переписанного участка удаляем его в том же коммите, чтобы
репозиторий показывал реальный прогресс.

Правила:

- Сначала проверяем ссылки (`grep` по классам, маршрутам, шаблонам), затем
  удаляем файл, маршрут, шаблон, тест и запись в документации.
- Не оставляем каталоги `legacy/`, `old/`, `backup/`, `*.bak`, закомментированные
  блоки и «временные» копии. Для истории есть `alpha`.
- Удаляем и старые тесты, которые проверяют только удалённый код; заменяем
  их тестами нового поведения.
- Совместимые обёртки пользовательского кода (`gg`, `sg`, `setTimeOut` и т. п.)
  — исключение: они остаются, пока владелец сам не перепишет свои методы.
- Если удаление ломает базовый модуль, модуль сначала переводится на новый
  сервис, затем старый файл удаляется. Нельзя держать два пути «навсегда».
- Каждое удаление фиксируется в журнале ниже: дата, что удалено, чем заменено.

Журнал удалений:

| Дата | Удалено | Замена |
| --- | --- | --- |
| 2026-10-26 | `public/spike.php`, `SpikeController`, `SpikeNote`, `spike.blade.php`, `SpikeTest`, `EloquentSpikeTest` | `public/index.php`, `YoyoDemoController`, `DatabaseTest`, `YoyoTest` |
| 2026-10-26 | старый `public/index.php` (`SiteApplication`) и `lib/core/SiteApplication.php` | новый `public/index.php` (kernel) |
| 2026-10-26 | `SettingController` и маршрут `/db/setting/{name}` | `SettingsRepository` + `DatabaseTest` |
| 2026-10-26 | прототип `modules/welcome` | `modules/core` + первый реальный core-модуль |
| 2026-10-26 | `templates/shoutbox/*`, `modules/shoutbox/*` | `modules/core/shoutbox` на `ModuleDb` + alpha-layout |
| 2026-10-26 | промежуточная `pages/shoutbox.blade.php` | `modules/core/shoutbox/views/index.blade.php` |
| 2026-10-26 | `modules/jobs`, `templates/jobs/*` | `modules/core/jobs` на core `Queue` |
| 2026-10-26 | `modules/actions_log`, `templates/actions_log/*` | `modules/core/actions_log` через `ActionLogRepository` |
| 2026-10-26 | `modules/settings`, `templates/settings/*` | `modules/core/settings` через `SettingsRepository` |
| 2026-10-26 | `modules/users`, `templates/users/*` | `modules/core/users` на новой `users` |
| 2026-10-26 | `modules/ui_kit`, `templates/ui_kit/*` | `modules/core/ui_kit` на `x-md.*` |
| 2026-10-26 | `modules/system_errors`, `templates/system_errors/*` | `modules/core/system_errors` + `ErrorRepository` |
| 2026-10-26 | `modules/locations`, `templates/locations/*` | `modules/core/locations` на новой `locations` |
| 2026-10-26 | `modules/scripts`, `templates/scripts/*` | `modules/core/scripts` на новой `scripts` |
| 2026-10-26 | `modules/classes`, `templates/classes/*` | `modules/core/classes` на новой `classes` |
| 2026-10-26 | `modules/properties`, `templates/properties/*` | `modules/core/properties` на новой `properties` |
| 2026-10-26 | `modules/methods`, `templates/methods/*` | `modules/core/methods` на новой `methods` |
| 2026-10-26 | `modules/objects`, `templates/objects/*` | `modules/core/objects` + `property_values` |
| 2026-10-26 | `modules/pvalues`, `templates/pvalues/*` | `modules/core/pvalues` на `property_values` |
| 2026-10-26 | `modules/history`, `templates/history/*` | `modules/core/history` на `property_history` |
| 2026-10-26 | `modules/events`, `templates/events/*` | `modules/core/events` на `property_events` и `PropertyValueService` |
| 2026-10-26 | `lib/core/Helpers/Events.php`, `lib/events.class.php`, `public/assets/modules/events/*` | журнал `property_events`; legacy `registerEvent()` убран, `Api\Dispatcher` не падает без него |
| 2026-10-26 | `modules/knowledge_base`, `templates/knowledge_base/*`, `public/assets/modules/knowledge_base/*` | `modules/core/knowledge_base` на `KnowledgeBase` + `league/commonmark`; `lib/knowledge_base.php` и `lib/markdown.php` оставлены только для `ai_assistant`/alpha-тестов |
| 2026-10-26 | `.env.bak.20261022-202814` | `.env.example` + `Config`; резервные копии `.env` не храним в git |
| 2026-10-26 | `public/admin.php`, `public/nf.php`, `modules/panel.class.php`, `modules/apps.php`, `modules/first_start.php`, `modules/inc_panel_ajax.php`, `templates/panel*`, `common_header_admin.html`, `lib/admin_router.php`, `lib/core/AdminApplication.php`, `lib/core/Api/Panel*`, `lib/dashboard_data.php` | новый `layouts.alpha` + `PanelRenderer`; alpha-панель остаётся в ветке `alpha` |
| 2026-10-26 | `resources/js/app.js`, `bootstrap.js`, `modules/admin_panel/*`, `vite*.config.js`, `public/assets/admin/admin.js|admin.css|charts|codeeditor|codehighlight` | `resources/js/mydomo/*`, `vite.mydomo.config.js`, `mydomo.js`/`mydomo.css` без jQuery и alpha-модулей |
| 2026-10-26 | `modules/api`, `templates/api/*`, `public/assets/modules/api/*` | `modules/core/api` на `module_api_webhooks`; legacy `public/api.php` и `lib/core/Api/*` оставлены только как вход alpha |
| 2026-10-26 | `modules/blockly_code`, `templates/blockly_code/*` | `x-md.code` (Alpine) в формах скриптов/методов; CodeMirror/alpha-редактор не переносим |
| 2026-10-26 | `modules/linkedobject`, `templates/linkedobject/*`, `public/assets/modules/linkedobject/*` | `src/LinkedObject/LinkedObjectService`, `/api/v1/panel/linked-object`, `x-md.linked-object` на Alpine |
| 2026-10-26 | старый `project_modules` и ad-hoc установка модулей | `module_registry`, `module_migrations`, `ModuleManifest`/`ModuleInstaller`, CLI `module:*`; пример `modules/example_sdk` |
| 2026-10-26 | heartbeat/состояние циклов в MySQL | `Worker`/`BackgroundTaskState` в Redis (`worker.task.*`, `worker.lock.*`), systemd-шаблон и `mydomo-agent`; таблица `background_tasks` не создаётся |
| 2026-10-27 | page-ассеты удалённых модулей (`public/assets/modules/panel`, `classes`, `users`, `settings`, `scripts`, `objects`, `properties`, `methods`, `jobs`, `locations`, `actions_log`, `shoutbox`, `system_errors`, `control_modules`), `templates/control_modules`, `tests/dashboard_browser_smoke.mjs` | страницы давно на Blade и общем `mydomo.js`/`mydomo.css`; alpha-ассеты грузились только удалённой панелью |
| 2026-10-27 | демо-файлы `cms/images/sample.jpg`, `cms/saverestore/mqtt.tgz` | не ссылаются ни код, ни шаблоны; медиа установки не трогались |
| 2026-10-27 | старый CLI `majordomo`, `lib/core/Console/*`, `tools/config.php`, `tools/install.php`, alpha-тесты `console_smoke`, `console_users_smoke` | `bin/mydomo` + `src/Console`; старый вход не является контрактом MyDoMo |
| 2026-10-27 | alpha-обновление `deploy.sh`, `lib/core/Deploy/*`, тесты `deploy_smoke`, `update_source_smoke` | обновление MyDoMo ещё проектируется; alpha-ветка сохраняет свой deploy |

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

`platform.php=8.2.0` — минимум для разрешения зависимостей, а не разрешение
работать на устаревшем патче. Production обязан быть на актуальном патче PHP;
`doctor` и deploy проверяют версию и предупреждают об устаревшей ветке.

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
| `clickfwd/yoyo` | `^0.15.1` | Livewire-подобные компоненты + htmx | Берём как UI-слой |
| `illuminate/filesystem` | `^12.0` | Blade и файлы | Транзитивно |
| `illuminate/events` | `^12.0` | События view/container | Транзитивно |
| `predis/predis` | `^2.4` | Redis без ext-redis | Берём как fallback |
| `monolog/monolog` | `^3.9` | Логи и каналы | Берём |
| `symfony/http-client` | `^7.4` | Исходящие HTTP-запросы | Берём |
| `symfony/rate-limiter` | `^7.4` | Ограничение частоты | Берём |
| `symfony/process` | `^7.4` | Процессы воркеров | Берём/транзитивно |
| `symfony/console` | `^7.4` | CLI `bin/mydomo` | Берём |
| `symfony/uid` | `^7.4` | ID/trace при необходимости | Оценить |
| `zircote/swagger-php` | `^5.0` | OpenAPI из атрибутов | Оценить |
| `league/commonmark` | `^2.10` | Markdown базы знаний | Взят, 2.10.4 |
| `dragonmantank/cron-expression` | `^3.4` | Cron-расписания, если появятся | Оценить |
| `illuminate/console` | `^12.0` | CLI на Symfony Console | Не берём: достаточно `symfony/console` |
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
| `symfony/var-dumper` | `^7.4` | Отладка в dev |
| `symfony/browser-kit` | `^7.4` | HTTP-тесты, если понадобятся |
| `symfony/dom-crawler` | `^7.4` | Разбор HTML в тестах |
| `symfony/css-selector` | `^7.4` | CSS-селекторы в тестах |

Не берём `roave/security-advisories` в основной набор: при фиксированном
`platform.php=8.2.0` он требует последний патч PHP и ломает установку на
минимальной версии. Вместо него — `composer audit`; подробности в 4.5.
Также не берём `pestphp/pest` (достаточно PHPUnit), `infection/infection`
(позже), `phpbench/phpbench` (после стабилизации), `filp/whoops` (свой error
handler).

### 4.4. Команды установки (spike)

Не выполнять все сразу. Сначала spike в `MyDoMo`:

```bash
composer require illuminate/routing:^12.0 illuminate/http:^12.0 \
  illuminate/container:^12.0 illuminate/database:^12.0 \
  illuminate/collections:^12.0 illuminate/cache:^12.0 \
  illuminate/redis:^12.0 illuminate/view:^12.0
composer require predis/predis:^2.4 monolog/monolog:^3.9 \
  symfony/console:^7.4 symfony/http-client:^7.4 \
  symfony/rate-limiter:^7.4 symfony/process:^7.4
composer require --dev phpunit/phpunit:^11.5 mockery/mockery:^1.6 \
  fakerphp/faker:^1.24 phpstan/phpstan:^2.1 \
  phpstan/phpstan-strict-rules:^2.0 phpstan/phpstan-deprecation-rules:^2.0 \
  rector/rector:^2.7 friendsofphp/php-cs-fixer:^3.95 \
  ergebnis/composer-normalize:^2.54 php-parallel-lint/php-parallel-lint:^1.4 \
  symfony/var-dumper:^7.4
```

Spike считается успешным, если одновременно работают: контейнер, один
маршрут, Blade-шаблон, Eloquent-модель, кэш, логгер, тест и `composer
check-platform-reqs` на PHP 8.2. Если склейка занимает больше двух недель,
возвращаемся к решению о полном Laravel или Symfony 7.4.

### 4.5. Проверка разрешения зависимостей

2026-10-26 сделан `composer update --dry-run` на `platform.php=8.2.0`.
Результат: набор Illuminate 12 + Symfony 7.4 + Monolog + Predis разрешается
без конфликтов (Illuminate 12.70.1, Symfony 7.4.21, Monolog 3.12.1,
Predis 2.4.1; 38 пакетов). Dev-набор без `roave/security-advisories` тоже
разрешается: PHPUnit 11.5.57, Rector 2.8.0, PHPStan 2.x.

`roave/security-advisories` в основной `composer.json` не добавляем: при
фиксированном `platform.php=8.2.0` он требует как минимум 8.2.35 (а для
8.3/8.4 — свои последние патчи) и ломает установку на минимальной версии.
Вместо него — `composer audit` в CI и обновление PHP-патча по регламенту.
Если нужен дополнительный барьер, вынести `roave` в отдельный nightly-job
с актуальным патчем PHP, но не в общую матрицу сборки.

### 4.6. Spike 0 и Stage 1 (выполнены локально)

В ветке `MyDoMo` собран вертикальный срез и подключён слой данных:

- `src/Foundation/Application.php` — контейнер, конфиг, Monolog, Blade,
  Illuminate Router, kernel, ленивый `DatabaseManager`;
- `src/Http/Kernel.php` — 404/405/500, `X-Request-Id`, без stack trace;
- `src/Http/Controller.php`, `src/Http/Concerns/CreatesResponses.php` —
  базовый контроллер и сахар ответов;
- `src/Database/DatabaseManager.php` — Illuminate Capsule, PDO, ping;
- `src/Database/Setting.php`, `SettingsRepository.php`, `Repository.php` —
  Eloquent-модель и репозиторий настроек;
- `src/Http/Controllers/HealthController.php`,
  `SettingController.php`, `YoyoDemoController.php`, `YoyoController.php`;
- `src/View/BladeView.php` — адаптер Blade Factory для Yoyo;
- `src/Yoyo/Counter.php` — тестовый компонент;
- `src/Http/Middleware/RequestIdMiddleware.php` — проверка pipeline;
- `bootstrap/app.php` — `Config::boot()` + параметры MySQL для `DatabaseManager`;
- `config/app.php`, `routes/web.php`, `resources/views/yoyo-demo.blade.php`,
  `resources/views/yoyo/counter.blade.php`;
- `public/index.php` — новый front controller; старый заменён, `spike.php`
  удалён;
- `tests/Feature/HttpKernelTest.php`, `DatabaseTest.php`, `YoyoTest.php` —
  12 тестов;
- `tests/Integration/MariaDbConnectionTest.php` — реальная MariaDB-проверка;
- `phpunit.xml`, `phpstan.neon`, `rector.php`, `.php-cs-fixer.dist.php`,
  `.github/workflows/ci.yml`.

Локальные результаты: `composer check` — 117 файлов lint, PHPStan без ошибок,
PHP-CS-Fixer 0 замечаний, Rector dry-run чистый, PHPUnit 12/12, MariaDB
integration 1/1 на одноразовом контейнере, ручные `/health`, `/`,
`/db/setting/...`, `/yoyo-demo` и POST `/yoyo` отвечают корректно. Старые
`config_smoke`/`routing_smoke`/`php majordomo about` тоже проходят.

Границы этапа: auth, сессии, полноценный middleware, кэш и модули ещё не
перенесены; Yoyo-update пока без CSRF/сессии. Следующий этап — auth/сессии,
глобальный middleware и перенос панели.

### 4.7. Yoyo: что это и как встраивать

**Статус:** `clickfwd/yoyo` 0.15.1 подключён и проверен в spike. Официальная
связка `jenssegers/blade` не подошла: она поддерживает Illuminate 9–11, а у
нас 12. Поэтому сделан собственный адаптер `src/View/BladeView.php` +
`YoyoBladeCompilerEngine`/`YoyoBladeDirectives`/`BladeViewProvider`.

Yoyo — не HTTP-роутер и не замена контроллерам. Это Livewire-подобный
full-stack-слой для серверных компонентов и htmx: страницу по-прежнему
отдаёт контроллер/Blade, а Yoyo обновляет отдельные компоненты через свой
endpoint. Поэтому архитектура такая:

- `public/index.php` → `Http\Kernel` → Illuminate Router → контроллер;
- контроллер возвращает Blade-страницу;
- в Blade-странице `@yoyo('component')` рендерит компонент;
- `POST /yoyo` (или `/panel/yoyo`) обслуживает обновления компонентов;
- модули регистрируют свои namespace/компоненты через манифест.

Что уже сделано:

- `BladeView` отдаёт Yoyo контейнер, finder и engine resolver;
- `YoyoBladeCompilerEngine` и `YoyoBladeDirectives` зарегистрированы вручную,
  без `jenssegers/blade`;
- `yoyo.js` 0.15.1 и `htmx.min.js` 1.9.4 лежат локально в
  `public/assets/vendor/`, CDN не используется;
- `@yoyo('counter')` рендерится на первичной загрузке, POST `/yoyo`
  возвращает обновлённый HTML;
- компонент `MajorDoMo\Yoyo\Counter` зарегистрирован в контейнере;
- `/yoyo` закрыт сеансом администратора и CSRF, страница передаёт токен
  в htmx;
- `/login` и `/logout` работают через users/Passwords и отдельную сессию
  `mydomo`.

Осталось:

- [ ] проверить компонент модуля через namespace
  `MajorDoMo\Modules\<Name>\Yoyo\`;
- [ ] решить, заменяет ли Yoyo `partLoad` полностью или только новые страницы;
- [ ] CSP, лимиты запросов и защита публичных свойств компонентов;
- [ ] тест на обновление компонента в браузере (htmx), а не только PHPUnit.

Yoyo остаётся UI-слоем: HTTP-роутинг, контроллеры и права — в ядре. Если
адаптер окажется слишком хрупким, оставляем Blade + HTMX/Alpine без Yoyo;
решение фиксируем после первых реальных страниц.

## 5. Этапы

| Этап | Результат | Гейт |
| --- | --- | --- |
| 0 | Решения, CI, инструменты, spike | CI зелёный, spike работает |
| 1 | Каркас: контейнер, конфиг, логи, ошибки, БД | Один запрос и одна модель |
| 2 | HTTP, роутер, middleware, auth/сессии/CSRF, Blade, Yoyo | `/panel/` и API-эндпоинт |
| 3 | Данные: Eloquent, репозитории, миграции | CRUD объекта и свойства |
| 4 | Кэш, очередь, события, планировщик | Операция через очередь |
| 5 | Модульный SDK и lifecycle | Фикстурный модуль |
| 6 | Базовые модули, волна 1 | Панель, классы, объекты |
| 7 | Базовые модули, волна 2 | Скрипты, события, пользователи |
| 8 | Базовые модули, волна 3 | Настройки, ошибки, задания |
| 9 | Интеграции и тяжёлые модули | MQTT, Zigbee, AI, market |
| 10 | Удаление legacy, миграция, релиз | Чистая установка и upgrade |

Вся работа идёт в одной ветке `MyDoMo`; отдельные feature-ветки не создаём.
Каждый этап — отдельный коммит, CI обязателен. После этапа 2 ветка должна
запускаться, даже если часть страниц ещё на старом коде: переходный период
допускается только через явный feature flag, а не через вечную двойную
поддержку.

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
- [x] API-срез: `/api/v1/health`, `/api/v1/about`, `/api/v1/modules` в
  `routes/api.php`.
- [x] `/api/v1/*` во всех входах отдаётся kernel (nginx dev/sample,
  `dev_server.php`); legacy `/api/*` остаётся на `api.php`.
- [x] `layouts.alpha` + `PanelRenderer`/`/status`; 2 теста.
- [x] API auth: `api_tokens`, Bearer/X-API-Key, `/api/v1/me` с ролями и
  permissions, `api:token`; 2 теста.
- [x] Страница `/roles`: роли/права, `can:users.manage`; 2 теста.
- [x] `/health` проверяет БД и кэш; `/status` — версия/БД/кэш/модули.
- [x] `doctor` + автосоздание `var/cache`, `var/log`; 1 тест.
- [x] Login throttle: 5 попыток/60 секунд по IP; 2 теста.
- [x] Docker-инструкция MyDoMo: сборка, health, CLI, reset pre-release-БД.
- [ ] Остальные роуты ядра: `/panel/...`, `/api/v2/...`, `/install`, `/ws`.
- [ ] Роуты модулей: `/panel/<id>/...`, `/api/v2/modules/<id>/...`;
  автоматический префикс и проверка владельца.
- [ ] Кэш маршрутов: `php majordomo route:cache`, `route:clear`, проверка
  конфликтов и запрет замыканий в кэшируемых маршрутах.
- [ ] 404/405/500: единый HTML для панели и JSON для API; `Allow` для 405.
- [ ] Обновить `docs/deploy/nginx.conf.sample`: фронт-контроллер
  `public/index.php`, статика/`cms`/`docs`, без старого `nf.php`.
- [ ] Запретить GET-мутации; CSRF для `POST/PUT/PATCH/DELETE`.
- [ ] Удалить `pd`, `view_mode`, `?action=...` из нового ядра.
- [ ] Добавить тонкий слой редиректов только для самых популярных старых
  URL, если это дешево; полная совместимость не требуется.

### 6.3. Данные и Eloquent

- [x] PDO/Capsule через `DatabaseManager`; новый слой без `mysqli`.
- [x] Базовая модель `src/Database/Model.php`: `$timestamps=false`,
  `$primaryKey='id'`, `$keyType='int'`, `$incrementing=true`.
- [x] `DatabaseManager::transaction()`; тест rollback на SQLite.
- [x] Все новые таблицы — lowercase snake_case, обязательные
  `created_at/updated_at/deleted_at`; alpha uppercase — legacy-исключение.
- [x] `Clock` на CarbonImmutable, `APP_TIMEZONE`, таймзона в boot; 1 тест.
- [ ] Модели ядра: `ObjectModel`, `ClassModel`, `PropertyModel`,
  `MethodModel`, `PropertyValue`, `Script`, `Event`, `Job`, `User`,
  `Setting`, `Module`, `History`.
- [ ] Репозитории для сложных выборок; контроллеры не пишут SQL.
- [x] Новые таблицы — lowercase snake_case, `id`/timestamps/soft deletes;
  модульные — `module_<id>_<table>`.
- [ ] Alpha-таблицы оставить как есть; при необходимости — адаптеры.
- [x] `Migrator` + журнал `migrations`, файлы `database/migrations/`,
  `db:migrate`, `db:status`; миграции `queue_jobs`, `module_shared_events`.
- [ ] Транзакции вокруг миграций/пакетов и откат.
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

- [x] `CacheStore` + фабрика stores: array, file, database.
- [x] TTL, `remember`, `rememberForever`, `has`, `forget`, `pull`,
  increment/decrement; массивы, `false`, `0`, `null`.
- [ ] Namespace с `install_hash`: сейчас `mydomo.<key>`.
- [x] Redis store через Predis/Illuminate Redis и `clear()` через SCAN по
  префиксу, без FLUSHDB (live Redis в тестах не поднимался).
- [ ] Запрет кэширования в незавершённой транзакции; инвалидация после commit.
- [x] Не использовать `FLUSHDB`/`FLUSHALL`; только SCAN по префиксу.
- [ ] Redis опционален; без него — database/file store с теми же гарантиями.
- [ ] `predis/predis` — fallback, если ext-redis нет; предпочитать phpredis.
- [x] CLI: `cache:clear`.
- [ ] CLI: `cache:stats`, `cache:warm`.
- [ ] Метрики hit/miss и размера в health-check.

### 6.5. Очередь, события, планировщик

- [x] Контракт `Queue` + долговечная `DatabaseQueue`: push/pop/ack/fail,
  задержка, reservation timeout, лимит попыток; 4 теста.
- [x] `JobRegistry`, `JobHandler`, `QueueWorker`; CLI `queue:work`.
- [ ] Redis-быстрый путь и typed jobs.
- [x] `ModuleBus`: retained events с TTL для публичного обмена core-модулей.
- [x] `Job` + `Queue::pushJob()`: класс задания задаёт имя и payload;
  worker ищет обработчик по имени.
- [ ] JSON Schema/валидация payload на границе задания.
- [ ] Идемпотентность, ключи повторов, dead-letter, лимиты попыток.
- [ ] Полосы `object:<id>` и `serial` сохранить; они уже доказали порядок.
- [ ] События: append-only `system_events`, PSR-14 для локальных слушателей,
  доставка модулям через очередь, outbox после commit.
- [ ] Планировщик: задания, повторы, отмена, trace; cron-expression только
  если появятся cron-правила.
- [x] CLI: `queue:stats`, `queue:work`, `queue:failed`, `queue:retry`,
  `db:migrate`, `db:status`, `api:token`.
- [ ] CLI: `jobs:list`, `worker:start`, `worker:stop`.
- [ ] Метрики: глубина, задержка, ошибки, возраст старейшего задания.
- [ ] Тесты: конкурентные воркеры, потеря соединения, повтор после сбоя,
  отсутствие двойного выполнения на уровне handlers.

### 6.6. Модульный SDK

> **Утверждено 2026-10-26:** [modules.md](../mydomo/modules.md). Сейчас
> работаем только с `core`-модулями; `third-party` — отдельный будущий этап.

- [x] Два типа: `core` и `third-party`; core нельзя удалить/отключить.
- [x] Спецификация структуры, манифеста, прав, БД и lifecycle.
- [x] `ModuleManager`: discover/register; переведён на `modules/core`.
- [x] `modules/welcome` удалён; `modules/core` — единственный core-root.
- [x] `ModuleDb`: MySQL/SQLite, префикс `module_<id>_`, allowlist, без raw SQL;
  тесты CRUD, чужой таблицы, отсутствия raw/join и SQLite-файла.
- [x] `ModuleBus`: retained events + TTL, namespaced topics, wildcard-подписки;
  таблица `module_shared_events` создаётся лениво.
- [ ] Интеграция страницы core-модуля в alpha-оболочку (`templates/panel.html`,
  `MDJUI`); **новый layout/дизайн не создаём**.
- [x] `ModuleManager` биндит `ModuleDb`/`ModuleBus` на маршрутах модуля.
- [ ] Первый порт: `shoutbox` в alpha-оболочке, затем `ui_kit`; старый код
  удаляем после рабочей интеграции.
- [ ] Миграции core-модулей при обновлении ядра (пока bootstrap-таблица).
- [ ] `module:list` показывает `type=core`, `removable=false`, `enabled=true`.
- [x] `module:remove`/`module:disable` запрещены для core.
- [ ] Следующий порт: `ui_kit`, затем остальные по порядку.
- [x] `module:check` — сканер старых вызовов для future third-party.
- [x] `permissions` манифеста регистрируются в таблице с владельцем; выдача
  ролям — вручную, admin получает через `*`.
- [x] Ассеты модуля публикуются в `public/assets/modules/<id>` при
  install/enable.
- [x] `module:remove --purge` удаляет объявленные таблицы и SQLite-данные;
  код модуля остаётся.
- [ ] Third-party lifecycle: обновление версии и миграции обновления.

### 6.7. Шаблоны и UI

- [x] Blade-компоненты на alpha-классах: button, icon, badge, card,
  section-heading, alert, avatar, empty-state, kpi, progress, status, field,
  input, textarea, select, checkbox, switch, disclosure, drawer, modal,
  skeleton, activity-feed, hint; секция в UI Kit. Внешний вид не менялся.
- [x] Первая страница: `pages.shoutbox` в alpha-shell, KPI/activity/hint/code;
  старые шаблоны Shoutbox удалены.
- [x] `BladeRenderer` — временный мост для alpha-shell; удалить после
  переноса shell на новый kernel.
- [ ] Перенести остальные `MDJUI`-компоненты на Blade; не держать две
  разметки.
- [x] `layouts.alpha` — перенос topbar/sidebar/меню на Blade с alpha-классами;
  тема, X-Ray, Health, выход.
- [x] Первая страница на `layouts.alpha`: `/status` (БД, кэш, версия, модули).
- [x] `PanelRenderer`/`PanelPage` — единая сборка страниц в alpha-layout;
  меню автоматически из core-модулей.
- [x] Веб-админка `/admin`: обзор, KPI, карточки модулей, быстрые действия;
  вход ведёт на `/admin`.
- [x] Вход на Blade: `md-auth`, логотип, `x-md.input/button/card/hint`.
- [x] `/admin/tokens`: выпуск и отзыв API-токенов в админке; 1 тест.
- [x] `/admin/profile`: имя и смена пароля; 1 тест.
- [x] Maintenance mode: файл-флаг, CLI, `/admin/maintenance`, health/login
  доступны; 1 тест.
- [x] `/admin/api`: живой список маршрутов Router; 1 тест.
- [x] Поиск в шапке и полный drawer: `PanelSearch` +
  `/api/v1/panel/search`, разделы по core-сущностям; 6 тестов.
- [x] Центр уведомлений: колокольчик, общий drawer, явное прочтение,
  `notify()` для сценариев; 7 тестов.
- [x] Старая панель и alpha-бандл удалены; новый shell `mydomo.js`/`mydomo.css`
  без jQuery/alpha-модулей; 2 теста.
- [x] `api`-модуль: входящие webhook, публичный `/api/v1/webhooks/{secret}`,
  секрет только SHA-256; 4 теста.
- [x] `knowledge_base`: страница `docs/**` с поиском, категориями, безопасным
  Markdown и оглавлением; 4 теста.
- [x] `/admin/modules`: реестр core-модулей; 1 тест.
- [x] Аудит: login success/failed/logout и user create/update/delete пишутся
  в `action_log`; 1 тест.
- [x] `shoutbox`: полный вертикальный срез (ModuleDb, Blade, alpha-layout).
- [x] `jobs`: очередь на `Queue`, pending/failed, retry/forget.
- [x] `actions_log`: журнал через `ActionLogRepository`, фильтр/удаление.
- [x] `settings`: список/поиск/редактирование через `SettingsRepository`;
  старый модуль удалён.
- [x] `users` новая lowercase-таблица; `UserRepository` и
  password_hash/password_verify; старый UserAccounts не используется.
- [x] `users`-модуль: список/создание/редактирование/soft delete; старый
  модуль и шаблоны удалены.
- [x] `ui_kit`: новый справочник `x-md.*`; старые шаблоны удалены.
- [x] UI Kit: `modal`/`drawer`/alerts на Alpine (`$store.mdModal`/
  `$store.mdDrawer`), Yoyo-счётчик и `@yoyo_scripts` на `/module/ui_kit`;
  Bootstrap JS и jQuery не используются; 4 теста.
- [ ] Yoyo-обёртки для остальных интерактивных блоков UI Kit.
- [x] `system_errors`: новая таблица, запись из Kernel, фильтр/soft delete.
- [x] `locations`: новая таблица, CRUD и приоритет; старый модуль удалён.
- [x] `scripts`: новая таблица, CRUD/редактор без запуска циклов; старый
  модуль удалён.
- [x] `classes`: новая таблица, CRUD; старый модуль удалён.
- [x] `properties`: новая таблица, CRUD; старый модуль удалён.
- [x] `methods`: новая таблица, CRUD без запуска; старый модуль удалён.
- [x] `objects`: `objects` + `property_values`, значения свойств; старый
  модуль удалён.
- [x] `gg`/`sg`/`getObject` для существующих объектов/свойств; 1 тест.
- [x] `pvalues`: значения свойств, правка/удаление; старый модуль удалён.
- [x] `history`: `property_history`, запись при `sg`, страница и `getHistory`.
- [x] users: назначение ролей в форме, `syncUserRoles`; 1 тест.
- [x] `callMethod`: поиск объекта/класса/метода и выполнение без eval; 1 тест.
- [x] Функции сценариев: `getGlobal`/`setGlobal`, `cm`/`runMethod`/
  `callMethodSafe`, `runScript`/`callScript`/`runScriptSafe`/`rs`;
  `ScriptRunner` по ID или названию, без eval; 5 тестов.
- [x] `knowledge_base`: `modules/core/knowledge_base` на `KnowledgeBase` +
  `league/commonmark`; front matter, категории, поиск, безопасный Markdown,
  ссылки и оглавление; старый модуль, шаблоны и page-ассеты удалены;
  4 теста, документация — knowledge_base.md.
- [x] `setTimeOut`/`clearTimeOut`: `runtime_timeouts`, явный `timeout:run`;
  1 тест.
- [x] Цепочка события свойства: `PropertyValueService` — единая запись,
  история по `keep_history`, журнал `property_events`, OnChange-метод,
  защита от циклов; `sg`, `/module/pvalues` и форма объекта идут одним путём;
  8 тестов, документация — [events.md](../mydomo/events.md).
- [x] Старая alpha-панель удалена: `public/admin.php`/`nf.php`, `panel.class.php`,
  `admin_router`, panel-шаблоны, `lib/core/Api/Panel*`, alpha-бандл и
  `resources/js/modules/admin_panel`; новый `mydomo.js`/`mydomo.css` без
  jQuery/alpha-модулей, 2 теста (`AdminAssetsTest`).
- [x] `events`: `modules/core/events` — журнал цепочки в alpha-layout,
  фильтры по типу/статусу; старый `modules/events` и `templates/events`
  удалены.
- [x] `x-md.select` рендерит `<option>` из слота — фильтры и формы модулей
  больше не показывают пустой список; проверено в `CoreEventsTest`.
- [x] Docker-стенд применяет `bin/mydomo db:migrate` и к непустой базе:
  новая миграция не теряется после пересборки образа.
- [x] API v1 доступен снаружи: nginx (dev и sample) и `dev_server.php`
  направляют `/api/v1/*` в kernel, legacy `/api/*` остаётся на `api.php`;
  `/api/v1/health` больше не возвращает legacy 503.
- [x] Alpha-shell поиск: `PanelSearch` + `/api/v1/panel/search` + drawer
  `#mdmGlobalSearchDrawer` в `layouts.alpha`; объекты, классы, свойства,
  методы, скрипты, пользователи, локации, core-модули; 6 тестов.
- [x] Центр уведомлений: `notifications`, `NotificationService`, `notify()`,
  API `GET/POST /api/v1/panel/notifications*` с CSRF, колокольчик и общий
  drawer `#mdAdminDrawerHost`; 7 тестов, документация — notifications.md.
- [x] Подключить `layouts.alpha` к остальным новым страницам и перенести
  остальные части `templates/panel.html` без изменения дизайна: все ядровые
  страницы идут через `PanelRenderer`, меню из `CoreSections`, хлебные крошки,
  поиск и уведомления в шапке (2026-10-26).
- [x] Сохранить `md-*`, Bootstrap 5, тёмную тему, токены и мобильную вёрстку:
  проверены 1500 px и 390 px, светлая и тёмная тема (2026-10-26).
- [ ] Yoyo spike: Blade-страница + компонент + `/yoyo` update endpoint;
  Yoyo остаётся UI-слоем, а не роутером (см. 4.7).
- [ ] htmx и `yoyo.js` — локальные ассеты Vite, без CDN; версия и лицензия
  зафиксированы.
- [ ] Заменить `partLoad` на Yoyo/HTMX или `fetch` + History API; решение —
  после spike, но jQuery в новом коде не используем.
- [ ] Удалить `jTemplate`/`parser` после переноса базовых модулей.
- [x] Ассеты: Vite, один core entry + page entries; без новых `<script>` в
  шаблонах. Сборка `npm run build:mydomo` даёт `mydomo.js`/`mydomo.css`
  (2026-10-26).
- [ ] Проверять 390 px, светлую/тёмную тему, клавиатуру и `prefers-reduced-motion`.

### 6.8. CLI

- [x] Новый `bin/mydomo` на Symfony Console: `about`, `health`, `cache:clear`,
  `module:list`, `route:list`, `queue:stats`; старый `php majordomo` пока не
  трогаем.
- [ ] Сохранить/перенести имена старых команд, где возможно.
- [x] `doctor`: PHP-расширения, runtime-каталоги, БД, кэш, timezone.
- [ ] Команды: `install`, `upgrade`, `db:migrate`,
  `db:seed`, `route:list`, `route:cache`, `route:clear`, `cache:*`,
  `queue:*`, `module:*`, `user:*`, `maintenance:*`.
- [ ] JSON-вывод, коды завершения, `--no-interaction`, `--verbose`.
- [ ] Не добавлять новые самостоятельные CLI-скрипты в корне.

### 6.9. Логи, ошибки, безопасность

- [x] Monolog `LoggerFactory`: каналы `app`, `http`, `security`; уровень из
  debug-флага.
- [ ] Остальные каналы (`api`, `db`, `queue`, `module`), ротация и уровень
  из `.env`.
- [ ] Сохранить `DebMes` как фасад; новые логи — только через PSR-3.
- [x] Единый `ErrorResponse`: JSON для API/htmx и текст для браузера,
  `request_id`, без stack trace даже в debug.
- [x] `RequestId` в HTTP-ответе и в `extra` каждой записи Monolog.
- [x] Пароли: `Passwords`/`password_hash`, перехеширование при входе.
- [x] Сессии: `HttpOnly`, `Secure`, `SameSite=Strict`, regenerate при входе.
- [x] CSRF для небезопасных методов; никаких GET-мутаций.
- [x] Rate limit логина: 5 попыток/60 секунд по IP, сброс после успеха.
- [ ] Rate limit API, публичных маршрутов и тяжёлых действий.
- [x] Токены API — SHA-256 в БД, выпуск/отзыв/срок, Bearer/X-API-Key.
- [ ] Ротация токенов и UI управления.
- [ ] Секреты модулей — шифрование sodium, ключ вне БД.
- [ ] Валидация загрузок, путей, JSON, размеров тела; запрет `..` и null-byte.
- [ ] `composer audit` в CI; `roave/security-advisories` — только отдельным
  nightly с актуальным патчем PHP, если понадобится.
- [x] `Authorization::can()` + middleware `can:`; admin/user/guest.
- [x] Таблицы `roles`, `permissions`, `role_permissions`, `user_roles`;
  миграция переносит администраторов; `RoleRepository`.
- [ ] UI управления ролями и назначение ролей пользователям.

### 6.10. Конфигурация, установка, обновление

- [x] `ConfigLoader` + `config/cache.php`, `config/queue.php`,
  `config/logging.php`; `app.php` остаётся базой.
- [ ] Типизированные объекты конфигурации и валидация обязательных ключей.
- [ ] `config/database.php`, `config/modules.php` + `.env`-оверрайды.
- [ ] Установщик: PDO, проверка PHP 8.2/расширений (`pdo_mysql`, `mbstring`,
  `intl`, `curl`, `json`, `openssl`, `fileinfo`, `sockets`; `redis` —
  опционально), utf8mb4, Redis optional.
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
- [ ] Rector: dry-run в CI, автофиксы отдельными коммитами.
- [ ] PHP-CS-Fixer: PSR-12 + правила проекта; `--dry-run` в CI.
- [ ] `php -l`/`parallel-lint`, `node --check`, `npm run build`.
- [ ] Покрытие: не гнаться за 100%; цель — 70% нового кода и все критичные
  сценарии (свойства, очередь, auth, API, модули).

## 7. Базовые модули

### 7.1. Порядок переписывания

| Волна | Модули | Почему |
| --- | --- | --- |
| 1 | `panel` (shell, сейчас `modules/panel.class.php`), `control_modules`, `users`, `settings` | Каркас, вход, права, конфиг |
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
- `api` — HTTP API переезжает в ядро; модуль остаётся экраном токенов,
  webhook и диагностики, а не реализацией роутера.
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

Пользовательский код — не модули. Его удобные обёртки сохраняем, даже если
под капотом будет другой сервис.

Сохранить как фасады над новыми сервисами:

- [x] `say` — уже пишет в Shoutbox core-модуля (level пока игнорируется);
- [x] Автоподключение helpers: core `src/Support/helpers/*.php`, модульные
  `helpers.php` из `module.json`; сервисы через `ModuleManager::service`.
- [x] `shoutbox_latest()` — пример публичного helper модуля; 1 тест.
- [ ] `getGlobal`, `setGlobal`, `gg`, `sg`;
- `setTimeOut`, `clearTimeOut`;
- `callMethod`, `callMethodSafe`, `callScript`, `runScript`;
- `say`, `sayReply`;
- `getObject`, `getObjectsByClass`, `getObjectsByProperty`;
- `getHistory`, `getHistoryValue`, `getHistoryAvg`, `getHistoryMin`,
  `getHistoryMax`, `getHistorySum`, `getHistoryCount`;
- `$this` в методах остаётся объектом MajorDoMo;
- `transcript` в текущем ядре не найден: если это пользовательская функция
  или обёртка из ваших сценариев, её сигнатуру нужно подтвердить и сохранить
  отдельно.

Прямой SQL в пользовательском коде запрещён полностью:

- `SQLSelect`, `SQLSelectOne`, `SQLExec`, `SQLInsert`, `SQLUpdate`;
- `$db`, `$mysqli`, `mysql_*`, `mysqli_*`, `PDO` и прямые `query()/exec()`;
- строки `SELECT ...`, `INSERT ...`, `UPDATE ...`, `DELETE ...`,
  `REPLACE ...`, `ALTER ...`, `DROP ...`, `TRUNCATE ...` в вызовах БД.

Вместо SQL пользователь получает `gg/sg`, методы объектов, сервисы `Core::*`
и SDK модуля. Для собственных данных модуль объявляет репозиторий/сервис,
а не отдаёт таблицу наружу.

Целевая проверка (пока не реализована):

- `UserCode\CodeGuard` должен разбирать PHP через `nikic/php-parser` и искать
  запрещённые функции, `$db` и SQL-вызовы; ошибка содержит строку и причину;
- проверка выполняется при сохранении метода/сценария и перед выполнением,
  чтобы старый код нельзя было обойти через импорт;
- планируемый `legacy:scan` должен показывать все найденные места и код
  возврата для CI;
- ошибки в пользовательском коде не роняют весь runtime: они логируются с
  trace и показываются в «Ошибках системы»;
- `$this`, типы и старые сигнатуры обёрток покрываются тестами на реальных
  сценариях/методах из демо-дампа.

Автоматически переписывать пользовательский код не будем: владелец портирует
свои методы и свойства вручную, а guard не даёт сохранить заведомо
несовместимый SQL.

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

### 9.3. Укрупнённое тестирование

Тесты не должны расти по одному на каждую кнопку и каждый шаг. Иначе через
полгода получим тысячи медленных тестов, которые никто не запускает.

- Unit — чистая логика без БД/HTTP: значения, роутинг-паттерны, пароли,
  конфиг, guard.
- Integration — группы сценариев на SQLite/MariaDB: вход, health, CRUD
  объекта, очередь, кэш, права.
- Feature/HTTP — один тест на контракт: успешный вход, неверный пароль,
  CSRF, 404/405, Yoyo update. Не дублировать каждый URL.
- E2E/браузер — только критические потоки: вход → панель → объект →
  свойство; вход → Yoyo-компонент. Не снимать скриншот каждой страницы.
- Багфикс — один регрессионный тест на конкретную ошибку, а не набор
  «на всякий случай».
- Ориентир: полный быстрый набор — до 2–3 минут; тяжёлые MariaDB/Redis —
  отдельным suite.

Ручная проверка в Docker дополняет тесты, но не заменяет их.

### 9.4. Локальная проверка и Docker

Основной быстрый просмотр — локальный dev server, без пересборки Docker
после каждого коммита:

```bash
php dev_server.php --host=127.0.0.1 --port=8080
php bin/mydomo about
php bin/mydomo health
```

Docker пересобираем только по отдельной просьбе или когда проверка нужна
именно в Linux-окружении. Тогда:

```bash
docker compose up -d --build app
docker compose ps
docker compose logs --tail=80 app
curl -I http://127.0.0.1/
curl -sS http://127.0.0.1/health
curl -I http://127.0.0.1/login
```

Docker — только локальная отладка; production-инструкция остаётся в
`docs/deploy/`.

## 10. Документация

- [x] Новая папка `docs/mydomo/`: README, architecture, functions, AGENTS,
  testing; модульная спецификация перенесена туда.
- [x] `docs/README.md` и корневой `AGENTS.md` ссылаются на MyDoMo-документацию.
- [x] Новые правила — `docs/mydomo/AGENTS.md`; старый `docs/agents/AGENTS.md`
  оставлен alpha-only и явно отделён в навигации.
- [x] Новый роутер/middleware описаны в `mydomo/architecture.md` и `api.md`;
  старый `docs/agents/routing.md` не заменяет новый контракт, route cache не реализован.
- [x] `docs/mydomo/database.md` — схема, Model, timestamps, явные фильтры
  soft delete, Clock и реальные ограничения миграций/времени.
- [x] `docs/mydomo/docker.md` — локальный стенд, проверки и фактическая
  инициализация БД; Docker не объявлен production-инсталлятором.
- [x] `docs/mydomo/cache.md`, `queue.md`, `events.md` — контракты и ограничения.
- [x] `configuration.md`, `authentication.md`, `api.md`, `console.md`,
  `core-sections.md`, `logging.md`, `limitations.md` — отдельные руководства.
- [ ] `docs/mydomo/upgrade.md` — alpha → MyDoMo, backup, откат.
- [ ] `docs/user/upgrade.md` — инструкция пользователю.
- [x] Обновлены `docs/README.md`, `docs/todo/README.md` и инструкция базы
  знаний; индексация новых документов использует существующий runtime.
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
- [ ] `lib/*.php` удалены; `lib/core/` перенесён в `src/`; в `src/Compat`
  остаётся только runtime пользовательских сценариев.
- [ ] Публичный API v1 проходит контрактные тесты; v2 документирован.
- [ ] PHPUnit, PHPStan, Rector, PHP-CS-Fixer, smoke-тесты и `npm run build`
  зелёные в CI на PHP 8.2 и 8.3.
- [ ] Нет известных критических уязвимостей; `composer audit` чист.
- [ ] Документация MyDoMo описывает архитектуру, модули, обновление и откат.
- [ ] Есть метрики: время ответа панели, API, запись свойства, очередь;
  нет регрессии больше согласованного порога.
- [ ] Тег `2.0.0` и инструкция по обновлению; alpha остаётся доступной.

## 13. Первоначальные варианты решений

Это история первоначальных рекомендаций. Фактически выбраны PHP 8.2+,
Illuminate components, Blade и новая схема ядровых страниц. Совместимость
API/пользовательского кода не считается готовой по одному этому списку;
актуальные нерешённые границы перечислены в начале плана и в справочнике.

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
