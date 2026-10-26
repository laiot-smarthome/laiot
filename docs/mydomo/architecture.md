---
title: MyDoMo — архитектура ядра
audience: agent, user
category: core
tags: mydomo, архитектура, kernel, container, сервисы, boot
description: Слои нового ядра MyDoMo, путь запроса, контейнер, сервисы и известные границы.
order: 2
---

# Архитектура MyDoMo

## 1. Слои

```text
public/index.php
  └─ bootstrap/app.php
      └─ Foundation\Application   boot, контейнер, конфиг, сервисы
          └─ Http\Kernel          request_id, ошибки, security headers
              └─ Illuminate Router
                  ├─ routes/web.php       ядровые разделы
                  ├─ routes/api.php       API v1
                  └─ modules/core/*/routes.php  MQTT/Zigbee
```

`src/Foundation/Application.php` — единственная сборка зависимостей.
Контроллеры и сервисы получают зависимости через контейнер, а не через
глобальные переменные.

## 2. Boot

`Application::boot()`:

1. создаёт `Illuminate\Container\Container`;
2. грузит `config/*.php` через `ConfigLoader`;
3. создаёт Monolog-каналы (`LoggerFactory`, `RequestContext`);
4. создаёт `DatabaseManager`, `CacheStore`, `DatabaseQueue`, `JobRegistry`;
5. создаёт сессию (`NativeSession`/`ArraySession`) и `Authorization`;
6. создаёт Blade/Yoyo и `PanelRenderer` для Blade-оболочки с визуалом alpha;
7. создаёт Router, алиасы middleware, Kernel;
8. грузит `routes/web.php`, `routes/api.php`, затем `ModuleManager`.

`boot()` не создаёт таблицы. При настроенной БД модульный SDK читает реестр
сторонних модулей; без конфигурации БД этот шаг пропускается, поэтому health
и публичные маршруты работают без подключения.

## 3. Запрос

`Kernel::handle()`:

- принимает/генерирует `X-Request-Id`, кладёт его в request attributes и
  `RequestContext`;
- передаёт запрос Router-у;
- 404/405/500 отдаёт через `ErrorResponse` (JSON для API/htmx, текст для
  браузера), без stack trace;
- добавляет `X-Request-Id`, `X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy`, `Permissions-Policy`.

Маршруты веб-слоя используют middleware `request-id`, `auth`, `csrf`;
для прав — `can:<permission>`, для входа — `throttle.login`.

`auth` сверяет пользователя с БД: запись должна существовать, не быть
удалённой и сохранять `is_admin = 1`. Обязательный строковый `stamp`
сопоставляется с SHA-256 текущего хеша пароля. Старые сессии отзываются
после удаления, снятия прав или смены пароля; смена пароля из профиля
обновляет отпечаток текущей сессии. Пропуска для сессий без `stamp` нет.

Debug выключен по умолчанию; веб-вход включает подробности исключений
только при типизированном `APP_DEBUG=true`. В режиме обслуживания остаются
доступными вход, выход, health и управление обслуживанием. Маршруты
управления сохраняют проверки `auth`, `can:settings.manage` и CSRF.

Внешний маршрут API один: `/api/v1/*` отдаётся фронт-контроллером
`public/index.php` (kernel). Legacy `/api/*` и `/api.php/*` остаются на
`public/api.php` — это старый alpha-контракт с webhook и своими обработчиками.
Nginx (`docker/app/nginx.conf`, `docs/deploy/nginx.conf.sample`) и
`dev_server.php` различают эти префиксы: иначе `/api/v1/health` попадал в
legacy API и возвращал 503 «install_required».

## 4. Сервисы

| Сервис | Назначение |
| --- | --- |
| `Application` | boot, контейнер, конфиг, доступ к сервисам |
| `DatabaseManager` | ленивое PDO/Capsule-соединение, schema, transaction |
| `Migrator` | файловые миграции и журнал `migrations` |
| `CacheStore` | array/file/database/redis, TTL, namespace `mydomo.` |
| `Queue` / `DatabaseQueue` | задания, резервирование, retry/dead-letter |
| `JobRegistry` / `QueueWorker` | именованные обработчики и worker |
| `HelperLoader` | автоподключение core/module helper-файлов |
| `MaintenanceMode` | флаг `var/maintenance.flag`, 503 для админки |
| `Clock` | CarbonImmutable, таймзона `APP_TIMEZONE` |
| `LoggerFactory` | каналы `app`, `http`, `security`, request_id |
| `Authorization` / `RoleRepository` | `can()`, роли и разрешения из БД |
| `ActionLogger` | аудит в `action_log`, не ломает действие |
| `UserRepository` | новая таблица `users`, password_hash/verify |
| `ApiTokenRepository` | API-токены, SHA-256, выпуск/отзыв |
| `ModuleManager` | core-модули, `modules/core` |
| `ModuleDb` | таблицы модуля `module_<id>_*`, без raw SQL |
| `ModuleBus` | retained events с TTL |
| `PropertyValueService` | единый путь записи значения: история, журнал, OnChange |
| `PropertyEventRepository` | журнал `property_events` |
| `PropertyEventChain` | контекст цепочки и предел вложенности |
| `PanelSearch` | поиск по core-сущностям для шапки админки |
| `NotificationService` | уведомления модулей/сценариев, прочтение и summary |
| `ScriptRunner` | запуск сценария по ID/названию с `$params` (без eval) |
| `KnowledgeBase` | индекс `docs/**`, категории, поиск и страницы базы знаний |
| `MarkdownRenderer` | безопасный GFM-рендер, ссылки и оглавление |
| `LinkedObjectService` | поиск объектов и свойства/методы класса для `x-md.linked-object` |
| `ModuleManifest` | валидация `module.json`, semver/requires/dependencies |
| `ModuleRegistry` / `ModuleInstaller` | реестр сторонних модулей, миграции и lifecycle |
| `Worker` / `BackgroundTaskState` | один процесс на модуль, задачи из манифеста, Redis-lock/heartbeat |
| `SystemdController` / `mydomo-agent` | привилегированный start/stop/restart worker-юнитов |

Запись значения свойства — не просто `UPDATE property_values`. Сервис
`PropertyValueService` сохраняет значение, пишет историю (если включена),
добавляет шаг в `property_events` и синхронно вызывает OnChange-метод свойства.
Повтор свойства в цепочке останавливает обработчик, но не откатывает значение.
Подробно — [events.md](events.md).

## 5. Ядро и модули

Ядровые разделы не являются модулями: у них нет `module.json`, `ModuleDb` и
lifecycle. Контроллеры — `src/Http/Controllers/Core`, представления —
`resources/views/pages/<id>`, маршруты — `routes/web.php`, меню —
`src/Panel/CoreSections.php`. Сюда входят объекты, классы, свойства, методы,
история, скрипты, события, локации, пользователи, настройки, журнал, ошибки,
очередь, API/webhooks, база знаний и оповещения.

Модулями остаются `mqtt`, `zigbee` и сторонние SDK-модули. Core-модули
лежат в `modules/core/<id>/`, сторонние — в `modules/<id>/`; маршруты
получают префикс `/module/<id>`, auth и `request-id`, на время запроса
контейнер получает их `ModuleDb`/`ModuleBus`. Lifecycle и формат манифеста —
[sdk.md](sdk.md). Старые alpha-модули без `module.json` игнорируются.

## 6. UI

Внешний вид — **только alpha**. Новый слой представлений:

- `resources/views/components/md/*` — Blade-компоненты с alpha-классами;
- `resources/views/ui-kit/blade.blade.php` — каталог компонентов, встроен в
  `/ui-kit`;
- `src/View/BladeRenderer.php` — временный мост для alpha-shell;
- `Http\PanelRenderer` + `PanelPage` — единая сборка страницы в alpha-layout;
  меню строится из `ModuleManager::menuItems()`;
- `resources/views/layouts/alpha.blade.php` — перенос alpha-shell на Blade
  (topbar/sidebar/меню, поиск в шапке и полный drawer результатов);
- `resources/views/components/md/code.blade.php` — редактор кода на Alpine
  с нумерацией строк (`x-md.code`), без CodeMirror; используется в формах
  скриптов и методов;
- `resources/views/components/md/linked-object.blade.php` + `mydomo/linked-object.js`
  — ядровой поиск объекта и контекст свойств/методов, без старого
  `modules/linkedobject`;
- `public/assets/admin/mydomo.js` и `mydomo.css` — единственный бандл нового
  ядра: Bootstrap CSS, Alpine и собственные vanilla-модули (тема, меню,
  поиск, уведомления, drawer). jQuery, `admin.js` и alpha-плагины в MyDoMo не
  подключаются и не собираются;
- интерактивные `x-md.*` — на Alpine: `$store.mdModal`/`$store.mdDrawer`,
  `@click`/`:class`/`:style`, без Bootstrap JS и `data-bs-*`;
- Yoyo: `/yoyo-demo` и счётчик в UI Kit; `GET /yoyo` закрыт auth, POST — CSRF;
  Yoyo только для UI-состояния, не для бизнес-операций;
- `src/Http/Controllers/PanelSearchController.php` — `/api/v1/panel/search`
  в конверте `{ok,data}` для `resources/js/mydomo/api.js`;
- `src/Http/Controllers/NotificationsController.php` — `/api/v1/panel/notifications`
  и `dismiss`; колокольчик и drawer строятся из `layouts.alpha` и
  `resources/js/mydomo/notifications.js`/`drawer.js`;
- `resources/views/login.blade.php` — alpha-вход (`md-auth`, логотип, `x-md`);
- `resources/views/pages/dashboard.blade.php` — веб-админка `/admin`;
- `resources/views/pages/status.blade.php` — первая страница нового kernel
  на `layouts.alpha`, `/status`;
- `resources/views/pages/roles.blade.php` — роли/права, `/roles`;
- `resources/views/pages/profile.blade.php` — профиль, `/admin/profile`;
- `resources/views/pages/api.blade.php` — список API-маршрутов, `/admin/api`;
- `resources/views/pages/modules.blade.php` — реестр модулей, `/admin/modules`;
- `resources/views/pages/events/index.blade.php` — журнал цепочки событий,
  `/events`;
- `src/Api/Webhooks/` — входящие webhook `/api` и публичный
  `/api/v1/webhooks/{secret}`; секрет хранится SHA-256, обработчики — сценарий
  или PHP через `ScriptRunner`/`CodeRunner`;
- `resources/views/pages/knowledge_base/` — база знаний `docs/**` в
  alpha-layout, `/knowledge-base`; Markdown рендерится
  `src/Docs/MarkdownRenderer.php`;
- `resources/views/pages/notifications/index.blade.php` — оповещения,
  `/notifications`.

Визуал берём из alpha, реализацию — нет: `layouts.alpha`, `mydomo.css` и
`mydomo.js` полностью новые. Alpha-разметка, `MDJUI`, `admin.js` и старая
панель — только архивный ориентир в ветке `alpha`.

## 7. Границы

- `alpha` — совместимая ветка; её модульный API не является контрактом MyDoMo.
- `public/api.php` — legacy-вход alpha; `public/admin.php`, `public/nf.php` и
  старая панель удалены из MyDoMo.
- `/api/v1/*` — контракт нового ядра; `/api/*` — legacy-вход alpha.
- Новый код не использует `$db`, `SQLSelect*`, `mysqli_*`, `PDO` напрямую.
- Пользовательские сценарии/модули получают только документированные
  обёртки; список — в [functions.md](functions.md).
