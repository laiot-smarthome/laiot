---
title: Ядро и модули MyDoMo
audience: user, agent
category: core
tags: mydomo, модули, core, sdk, ModuleDb, ModuleBus, CoreSections
description: Разделение ядровых разделов и модулей MyDoMo, SDK, ModuleDb и порядок переноса.
order: 6
---

# Ядро и модули

## 1. Две категории

| Категория | Где живёт | Lifecycle | Примеры |
| --- | --- | --- | --- |
| Ядровой раздел | `src/Http/Controllers/Core`, `resources/views/pages`, `routes/web.php` | часть ядра | объекты, классы, скрипты, события, очередь, API, база знаний |
| Модуль | `modules/core/<id>` или `modules/<id>` | SDK install/enable/disable | MQTT, Zigbee, сторонние |

Ядровые разделы **не являются модулями**: у них нет `module.json`,
`ModuleDb`, `ModuleBus` и lifecycle. Они не появляются в `module:list` и
`/admin/modules`. Меню строится `src/Panel/CoreSections.php`.

Модулями остаются только MQTT, Zigbee и сторонние SDK-модули.

## 2. Ядровые разделы

URL без `/module`:

| Раздел | URL |
| --- | --- |
| объекты | `/objects` |
| классы | `/classes` |
| свойства | `/properties` |
| методы | `/methods` |
| значения | `/values` |
| история | `/history` |
| локации | `/locations` |
| скрипты | `/scripts` |
| события | `/events` |
| очередь | `/jobs` |
| база знаний | `/knowledge-base` |
| пользователи | `/users` |
| настройки | `/settings` |
| журнал | `/journal` |
| ошибки | `/errors` |
| API/webhooks | `/api` |
| оповещения | `/notifications` |
| UI Kit | `/ui-kit` |

Контроллеры — `MajorDoMo\Http\Controllers\Core`, views — `pages.<id>.*`.
Доступ к данным идёт через ядровые репозитории/сервисы, а не `ModuleDb`.

## 3. Модули

```text
modules/core/mqtt/       # type=core, всегда включён
modules/core/zigbee/     # type=core, всегда включён
modules/<id>/            # сторонний, install/disable/remove
```

Структура модуля и `module.json` описаны в [sdk.md](sdk.md). Модуль получает:

- префикс маршрутов `/module/<id>`;
- `ModuleDb` только для своих таблиц `module_<id>_*`;
- `ModuleBus` для retained-событий;
- запись в `module_registry` (сторонние);
- меню в группе «Модули».

Core-модули (MQTT/Zigbee) нельзя disable/remove; они обновляются вместе с
ядром.

## 4. Данные

- ядровые таблицы — lowercase без префикса (`objects`, `property_events`,
  `api_webhooks`, `shared_events` и т.д.);
- таблицы модулей — `module_<id>_<table>`, доступ только через `ModuleDb`;
- `module_registry`/`module_migrations` — SDK-реестр;
- `shared_events` — ядровая шина `ModuleBus`.

## 5. Frontend

- ядровой layout `resources/views/layouts/alpha.blade.php`;
- ядровые views `resources/views/pages/<id>`;
- модульные views — namespace `<id>::`;
- `x-md.*` компоненты общие; модуль не создаёт свой layout.

## 6. Порядок переноса

1. Ядровые разделы уже перенесены из `modules/core` в ядро.
2. Следующие шаги:
   - MQTT и Zigbee как core-модули SDK;
   - оставшийся старый backend `modes`, `pinghosts`, `xray`,
     `control_modules`, `market`, `saverestore`, `ai_assistant` — в ядровые
     сервисы/разделы;
   - удаление старых `modules/<id>` после переноса.
3. Циклы переводятся на `worker`/`core-worker`; старые `cycle_*` не трогаются
   до готовности systemd-слоя.

## 7. Текущее состояние

- `CoreSections` — источник меню ядра;
- `modules/core` пуст, готов для MQTT/Zigbee;
- `modules/example_sdk` — пример SDK, не установлен по умолчанию;
- старые `modules/<id>` без `module.json` не подключаются;
- `/admin/modules` показывает только SDK-модули.
