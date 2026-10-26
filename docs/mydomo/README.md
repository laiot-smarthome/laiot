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

**База ядра и веб-админка готовы.** Ядровые разделы перенесены из
`modules/core` в `src/Http/Controllers/Core` и `resources/views/pages`.
Старая alpha-панель и её бандл удалены из MyDoMo.

Уже работает в новом ядре:

- `public/index.php` → `Application` → `Kernel` → Illuminate Router;
- сессия `mydomo`, вход/выход, CSRF, rate limit входа;
- `Authorization`/`can:`, таблицы `roles`/`permissions`/`user_roles`,
  `/roles`, назначение ролей в users;
- API-токены (`api_tokens`, Bearer/X-API-Key, `/api/v1/me` с правами,
  `/admin/tokens`); `/api/v1/*` обслуживает новое ядро, legacy `/api/*` —
  старый `api.php`;
- поиск в шапке админки и полный drawer `/api/v1/panel/search`
  (объекты, классы, свойства, методы, скрипты, пользователи, локации, модули);
- центр уведомлений: `notifications`, `NotificationService`, `notify()`,
  колокольчик и drawer `/api/v1/panel/notifications`;
- входящие webhook `/api`: секрет в URL, методы, сценарий/PHP и журнал
  последнего вызова; публичный вход `/api/v1/webhooks/{secret}`;
- фоновый runtime: `worker <module>` (SDK-модули, один процесс на модуль,
  задачи из `module.json`, Redis-lock/heartbeat), отдельные
  `core-worker queue|timeouts` и root-агент `mydomo-agent` для systemd
  start/stop/restart без доступа PHP к `/etc`; Redis для runtime обязателен;
- linked object — ядровой `LinkedObjectService`, API
  `/api/v1/panel/linked-object` и Alpine-компонент `x-md.linked-object`;
- база знаний `/knowledge-base`: `docs/**`, front matter, поиск, безопасный
  Markdown и оглавление;
- единые 404/405/500, `request_id`, security headers, maintenance mode;
- `ModuleManager`/`ModuleDb`/`ModuleBus` — только для MQTT, Zigbee и
  сторонних SDK-модулей;
- кэш `array`/`file`/`database`/`redis`;
- очередь `DatabaseQueue`, worker, failed/retry/pending, typed jobs;
- CLI: `about`, `doctor`, `health`, `route:list`, `queue:*`, `db:*`,
  `module:*`, `worker`, `core-worker`, `api:token`, `maintenance`;
- `/health` (БД+кэш), `/status`, `/admin` (Blade-админка);
- `PanelRenderer` + `layouts.alpha` на `mydomo.css`/`mydomo.js`, меню из
  `CoreSections` + модулей; старая панель `/panel/` и alpha-бандл удалены;
- вход, UI Kit `/ui-kit`, профиль, API-маршруты, реестр модулей;
- пользовательские обёртки: `say`, `gg`/`sg`, `getGlobal`/`setGlobal`,
  `getObject`, `getHistory`, `callMethod`/`cm`/`runMethod`/
  `callMethodSafe`, `runScript`/`callScript`/`runScriptSafe`/`rs`,
  `setTimeOut`, `clearTimeOut`, `notify`;
- цепочка события свойства: запись → история → `property_events` →
  OnChange-метод, защита от циклов и журнал `/events`;
- Carbon/Clock, `APP_TIMEZONE`, lowercase-схема + timestamps + soft deletes.

Ядровые разделы: `objects`, `classes`, `properties`, `methods`, `pvalues`,
`history`, `locations`, `scripts`, `events`, `jobs`, `knowledge_base`,
`users`, `settings`, `actions_log`, `system_errors`, `api`, `notifications`,
`ui_kit`. Они не являются модулями.

Ещё не закрыто:

- старый backend-код `modes`, `pinghosts`, `xray`, `control_modules`,
  `market`, `saverestore`, `ai_assistant` — переносится в ядровые сервисы
  по одному; `mqtt` и `zigbee` станут core-модулями SDK;
- UI Kit: Alpine-компоненты и Yoyo-счётчик работают; остались Yoyo-обёртки;
- установка/обновление ядра (ручная кнопка), полный API v2;
- live Redis-проверка;
- systemd-юниты не устанавливаются автоматически: `mydomo-agent install` и
  `systemctl` — этап deploy.

## Что читать

1. [Архитектура](architecture.md) — слои, boot, запрос, сервисы.
2. [Функции и обёртки](functions.md) — публичные API, CLI, будущие
   пользовательские функции.
3. [Цепочка событий свойства](events.md) — запись, история, OnChange,
   `property_events` и защита от циклов.
4. [Центр уведомлений](notifications.md) — `notifications`, `notify()`, API,
   колокольчик и правила прочтения.
5. [База знаний](knowledge_base.md) — `docs/**` в админке, front matter и
   безопасный Markdown.
6. [Правила для агентов](AGENTS.md) — как читать, менять и проверять.
7. [Тестирование](testing.md) — укрупнённые тесты, dev server, Docker.
8. [Модульная система](modules.md) — core/third-party, `ModuleDb`, `ModuleBus`.
9. [SDK модулей](sdk.md) — `module.json`, lifecycle, миграции, CLI.
10. [Фоновый runtime](runtime.md) — worker, Redis-состояния, systemd-агент
    и ручное обновление.
11. [База и время](database.md) — lowercase, timestamps, soft deletes, Carbon.
12. [Docker-стенд](docker.md) — локальная отладка и проверки.
13. [Мастер-план](../todo/mydomo.md) — этапы и текущий прогресс.

## Правило обновления

Меняешь публичный API, обёртку, команду или контракт — обнови
`functions.md`/`architecture.md` в том же коммите. Не оставляй «сделано» без
проверки и не описывай alpha-поведение как новое.
