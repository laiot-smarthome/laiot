---
title: Документация MyDoMo
audience: user, agent
category: general
tags: документация, навигация, mydomo
description: Карта актуальной документации ветки MyDoMo; alpha-документация удалена и доступна только в ветке alpha.
order: 1
---

# Документация MyDoMo

В ветке осталась только документация нового ядра. Alpha-runtime и его
руководства (`agents/`, `user/`, `deploy/`, `archive/`) удалены; при
необходимости смотрите ветку `alpha` на GitHub.

## С чего начать

- [MyDoMo: карта ядра](mydomo/README.md) — что реализовано и что читать.
- [Архитектура](mydomo/architecture.md) — путь запроса и границы слоёв.
- [Мастер-план](todo/mydomo.md) — этапы, решения и журнал изменений.
- [Правила для агентов](mydomo/AGENTS.md) — как менять код и документацию.

## Разделы

| Задача | Документ |
| --- | --- |
| Запуск, установка, локальный сервер | README репозитория в корне |
| Конфигурация и `.env` | [configuration.md](mydomo/configuration.md) |
| Вход, сессии, роли, токены | [authentication.md](mydomo/authentication.md) |
| Страницы и контроллеры ядра | [core-sections.md](mydomo/core-sections.md) |
| Интерфейс и компоненты | [admin-ui.md](mydomo/admin-ui.md) |
| Свойства, история, OnChange | [events.md](mydomo/events.md) |
| Функции и обёртки | [functions.md](mydomo/functions.md) |
| Уведомления | [notifications.md](mydomo/notifications.md) |
| API и webhook | [api.md](mydomo/api.md) |
| Кэш | [cache.md](mydomo/cache.md) |
| Очередь и задания | [queue.md](mydomo/queue.md) |
| CLI | [console.md](mydomo/console.md) |
| Модули и SDK | [modules.md](mydomo/modules.md), [sdk.md](mydomo/sdk.md) |
| Фоновый runtime и systemd | [runtime.md](mydomo/runtime.md) |
| База данных и миграции | [database.md](mydomo/database.md) |
| Логи и диагностика | [logging.md](mydomo/logging.md) |
| Тесты | [testing.md](mydomo/testing.md) |
| Локальный Docker-стенд | [docker.md](mydomo/docker.md) |
| База знаний | [knowledge_base.md](mydomo/knowledge_base.md) |
| Комментарии в коде | [code-comments.md](mydomo/code-comments.md) |
| Ограничения | [limitations.md](mydomo/limitations.md) |

## Правила

- Текст на русском; технические имена остаются как есть.
- У файла — front matter (`title`, `audience`, `category`, `description`,
  `tags`, `order`).
- Ссылки относительные, пути — от корня репозитория.
- Документация меняется в том же изменении, что и код.
- Проверка: `php tests/docs_quality_smoke.php`.
