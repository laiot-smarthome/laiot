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

Идёт **core-gate**: сначала закрываем ядро, затем переносим модули по одному.
Модули пока заморожены; страница Shoutbox на Blade — промежуточная.

Уже работает в новом ядре:

- `public/index.php` → `Application` → `Kernel` → Illuminate Router;
- сессия `mydomo`, вход/выход, CSRF, `Authorization`/`can:`;
- единые 404/405/500, `request_id`, security headers;
- `ModuleManager`, `ModuleDb` (MySQL/SQLite), `ModuleBus` (retained events);
- кэш `array`/`file`/`database`/`redis`;
- очередь `DatabaseQueue`, worker, failed/retry, typed jobs;
- CLI `bin/mydomo`: `about`, `health`, `route:list`, `queue:*`, `db:*`,
  `module:list`;
- API v1: `/api/v1/health`, `/api/v1/about`;
- Blade-компоненты `x-md.*` с alpha-разметкой и alpha UI Kit.

Ещё не закрыто:

- alpha-layout целиком на Blade/Yoyo (сейчас alpha-shell остаётся);
- таблица ролей/разрешений (сейчас роль в сессии);
- полный API и установка/обновление;
- пользовательские обёртки нового ядра;
- перенос core-модулей.

## Что читать

1. [Архитектура](architecture.md) — слои, boot, запрос, сервисы.
2. [Функции и обёртки](functions.md) — публичные API, CLI, будущие
   пользовательские функции.
3. [Правила для агентов](AGENTS.md) — как читать, менять и проверять.
4. [Тестирование](testing.md) — укрупнённые тесты, dev server, Docker.
5. [Модульная система](modules.md) — core/third-party, `ModuleDb`, `ModuleBus`.
6. [База и время](database.md) — lowercase, timestamps, soft deletes, Carbon.
7. [Мастер-план](../todo/mydomo.md) — этапы и текущий прогресс.

## Правило обновления

Меняешь публичный API, обёртку, команду или контракт — обнови
`functions.md`/`architecture.md` в том же коммите. Не оставляй «сделано» без
проверки и не описывай alpha-поведение как новое.
