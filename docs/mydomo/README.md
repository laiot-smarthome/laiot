---
title: MyDoMo — документация нового ядра
audience: user, agent
category: core
tags: mydomo, ядро, документация, архитектура, функции, модули
description: Точка входа в документацию MyDoMo: что уже работает, что ещё в alpha, как читать и обновлять новые инструкции.
order: 1
---

# MyDoMo — документация нового ядра

Эта папка — **только для MyDoMo**. Старые инструкции `docs/agents/`,
`docs/user/` и `docs/deploy/` описывают `alpha` и остаются источником по
legacy-коду. Если документ относится к новому ядру, он живёт здесь.

## Статус

**Core-gate закрыт по базовым слоям**, но не по всей alpha-совместимости:
два модуля уже перенесены, пользовательские обёртки — частично.

Уже работает в новом ядре:

- `public/index.php` → `Application` → `Kernel` → Illuminate Router;
- сессия `mydomo`, вход/выход, CSRF, rate limit входа;
- `Authorization`/`can:`, таблицы `roles`/`permissions`/`user_roles`,
  страница `/roles`;
- API-токены (`api_tokens`, Bearer/X-API-Key, `/api/v1/me` с правами);
- единые 404/405/500, `request_id`, security headers;
- `ModuleManager`, `ModuleDb` (MySQL/SQLite), `ModuleBus` (retained events);
- кэш `array`/`file`/`database`/`redis`;
- очередь `DatabaseQueue`, worker, failed/retry/pending, typed jobs;
- CLI `bin/mydomo`: `about`, `doctor`, `health`, `route:list`, `queue:*`,
  `db:*`, `module:list`, `api:token`;
- `/health` проверяет БД и кэш, `/status` — версия/БД/кэш/модули;
- `PanelRenderer` + `layouts.alpha`, меню из core-модулей;
- веб-админка `/admin` и вход на Blade-компонентах;
- пользовательская `say()` пишет в Shoutbox;
- core/module helpers подключаются автоматически; модуль публикует функции
  через `helpers.php` и сервис модуля (`shoutbox_latest()`);
- модули `shoutbox` и `jobs` перенесены полностью, старый код удалён;
- Blade-компоненты `x-md.*` с alpha-разметкой;
- Carbon/Clock, `APP_TIMEZONE`, lowercase-схема + timestamps + soft deletes.

Ещё не закрыто:

- пользовательские `gg`, `sg`, `setTimeOut`, `callMethod`, `getObject`,
  `getHistory` — нужны объекты/свойства/методы как core-сервисы;
- полная alpha-shell (notifications/console/search parity);
- установка/обновление, полный API v2;
- остальные core-модули;
- live Redis-проверка.

## Что читать

1. [Архитектура](architecture.md) — слои, boot, запрос, сервисы.
2. [Функции и обёртки](functions.md) — публичные API, CLI, будущие
   пользовательские функции.
3. [Правила для агентов](AGENTS.md) — как читать, менять и проверять.
4. [Тестирование](testing.md) — укрупнённые тесты, dev server, Docker.
5. [Модульная система](modules.md) — core/third-party, `ModuleDb`, `ModuleBus`.
6. [База и время](database.md) — lowercase, timestamps, soft deletes, Carbon.
7. [Docker-стенд](docker.md) — локальная отладка и проверки.
8. [Мастер-план](../todo/mydomo.md) — этапы и текущий прогресс.

## Правило обновления

Меняешь публичный API, обёртку, команду или контракт — обнови
`functions.md`/`architecture.md` в том же коммите. Не оставляй «сделано» без
проверки и не описывай alpha-поведение как новое.
