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

**База ядра и веб-админка готовы.** Перенесено **18 core-модулей**;
осталось 9 старых модулей. Старая alpha-панель и её бандл удалены из
MyDoMo. Пользовательские обёртки работают.

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
- входящие webhook `/module/api`: секрет в URL, методы, сценарий/PHP и
  журнал последнего вызова; публичный вход `/api/v1/webhooks/{secret}`;
- linked object — ядровой `LinkedObjectService`, API
  `/api/v1/panel/linked-object` и Alpine-компонент `x-md.linked-object`
  (поиск объекта, свойства/методы класса); старый модуль удалён;
- база знаний `/module/knowledge_base`: `docs/**`, front matter, поиск,
  безопасный Markdown и оглавление;
- единые 404/405/500, `request_id`, security headers, maintenance mode;
- `ModuleManager`, `ModuleDb` (MySQL/SQLite), `ModuleBus` (retained events);
- автозагрузка core/module helpers; модуль публикует функции через
  `helpers.php` и сервис модуля;
- кэш `array`/`file`/`database`/`redis`;
- очередь `DatabaseQueue`, worker, failed/retry/pending, typed jobs;
- CLI: `about`, `doctor`, `health`, `route:list`, `queue:*`, `db:*`,
  `module:list`, `api:token`, `maintenance`, `timeout:run`;
- `/health` (БД+кэш), `/status`, `/admin` (Blade-админка);
- `PanelRenderer` + `layouts.alpha` на новом CSS/JS (`mydomo.css`/`mydomo.js`),
  меню из core-модулей; старая панель `/panel/` и alpha-бандл удалены;
- вход, UI Kit, профиль, API-маршруты, реестр модулей;
- пользовательские обёртки: `say`, `gg`/`sg`, `getGlobal`/`setGlobal`,
  `getObject`, `getHistory`, `callMethod`/`cm`/`runMethod`/
  `callMethodSafe`, `runScript`/`callScript`/`runScriptSafe`/`rs`,
  `setTimeOut`, `clearTimeOut`, `notify`;
- цепочка события свойства: запись → история → `property_events` →
  OnChange-метод, защита от циклов и журнал `/module/events`;
- Carbon/Clock, `APP_TIMEZONE`, lowercase-схема + timestamps + soft deletes.

Перенесённые модули: `shoutbox`, `jobs`, `actions_log`, `settings`, `users`,
`ui_kit`, `system_errors`, `locations`, `scripts`, `classes`, `properties`,
`methods`, `objects`, `pvalues`, `history`, `events`, `api`, `knowledge_base`.

Ещё не закрыто:

- 9 старых модулей: modes, pinghosts, mqtt, zigbee, xray,
  ai_assistant, control_modules, market, saverestore;
- UI Kit: Alpine-компоненты (`modal`, `drawer`, alerts) и Yoyo-счётчик
  работают; остались Yoyo-обёртки для остальных блоков;
- установка/обновление, полный API v2;
- live Redis-проверка;
- автоматический запуск timeout/queue — только явные команды, циклы не трогаем.

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
9. [База и время](database.md) — lowercase, timestamps, soft deletes, Carbon.
10. [Docker-стенд](docker.md) — локальная отладка и проверки.
11. [Мастер-план](../todo/mydomo.md) — этапы и текущий прогресс.

## Правило обновления

Меняешь публичный API, обёртку, команду или контракт — обнови
`functions.md`/`architecture.md` в том же коммите. Не оставляй «сделано» без
проверки и не описывай alpha-поведение как новое.
