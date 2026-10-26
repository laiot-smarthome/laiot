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
                  ├─ routes/web.php
                  ├─ routes/api.php
                  └─ modules/core/*/routes.php
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
6. создаёт Blade/Yoyo, `PanelRenderer` нет — alpha-shell остаётся снаружи;
7. создаёт Router, алиасы middleware, Kernel;
8. грузит `routes/web.php`, `routes/api.php`, затем `ModuleManager`.

`boot()` не открывает соединение с БД и не создаёт таблицы: health и тесты
без БД должны работать.

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
| `Clock` | CarbonImmutable, таймзона `APP_TIMEZONE` |
| `LoggerFactory` | каналы `app`, `http`, `security`, request_id |
| `Authorization` / `RoleRepository` | `can()`, роли и разрешения из БД |
| `UserRepository` | новая таблица `users`, password_hash/verify |
| `ApiTokenRepository` | API-токены, SHA-256, выпуск/отзыв |
| `ModuleManager` | core-модули, `modules/core` |
| `ModuleDb` | таблицы модуля `module_<id>_*`, без raw SQL |
| `ModuleBus` | retained events с TTL |

## 5. Модули

Core-модули лежат в `modules/core/<id>/` и не удаляются/не отключаются.
Маршруты получают префикс `/module/<id>`, auth и `request-id`; на время
запроса контейнер получает `ModuleDb`/`ModuleBus` этого модуля.

`modules/` зарезервирован под будущие third-party-модули. Старые модули в
`modules/<id>/` — старые модули, которые ещё не перенесены; после переноса
удаляются, без редиректов и runtime-совместимости.

## 6. UI

Внешний вид — **только alpha**. Новый слой представлений:

- `resources/views/components/md/*` — Blade-компоненты с alpha-классами;
- `resources/views/ui-kit/blade.blade.php` — демо в существующем UI Kit;
- `src/View/BladeRenderer.php` — временный мост для alpha-shell;
- `Http\PanelRenderer` + `PanelPage` — единая сборка страницы в alpha-layout;
  меню строится из `ModuleManager::menuItems()`;
- `resources/views/layouts/alpha.blade.php` — перенос alpha-shell на Blade
  (topbar/sidebar/меню);
- `resources/views/login.blade.php` — alpha-вход (`md-auth`, логотип, `x-md`);
- `resources/views/pages/dashboard.blade.php` — веб-админка `/admin`;
- `resources/views/pages/status.blade.php` — первая страница нового kernel
  на `layouts.alpha`, `/status`;
- `resources/views/pages/roles.blade.php` — роли/права, `/roles`;
- `resources/views/pages/shoutbox.blade.php` — промежуточная страница.

Новый layout/CSS не создаём. Alpha-shell и `MDJUI` остаются эталоном, пока
панель не будет перенесена целиком.

## 7. Границы

- `alpha` — совместимая ветка; её модульный API не является контрактом MyDoMo.
- `public/api.php`, `public/admin.php`, `public/nf.php` — legacy-входы.
- Новый код не использует `$db`, `SQLSelect*`, `mysqli_*`, `PDO` напрямую.
- Пользовательские сценарии/модули получают только документированные
  обёртки; список — в [functions.md](functions.md).
