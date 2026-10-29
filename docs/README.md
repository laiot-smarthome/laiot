---
title: Документация LaIOT
audience: user, agent
category: general
tags: документация, навигация, laiot
description: Карта актуальной документации ветки LaIOT; alpha-документация удалена и доступна только в ветке alpha.
order: 1
---

# Документация LaIOT

В ветке осталась только документация нового ядра. Alpha-runtime и его
руководства (`agents/`, `user/`, `deploy/`, `archive/`) удалены; при
необходимости смотрите ветку `alpha` на GitHub.

## С чего начать

- [LaIOT: карта ядра](laiot/README.md) — что реализовано и что читать.
- [Архитектура](laiot/architecture.md) — путь запроса и границы слоёв.
- [Мастер-план](todo/laiot.md) — этапы, решения и журнал изменений.
- [Правила для агентов](laiot/AGENTS.md) — как менять код и документацию.

## Разделы

| Задача | Документ |
| --- | --- |
| Запуск, установка, локальный сервер | README репозитория в корне |
| Конфигурация и `.env` | [configuration.md](laiot/configuration.md) |
| Вход, сессии, роли, токены | [authentication.md](laiot/authentication.md) |
| Страницы и контроллеры ядра | [core-sections.md](laiot/core-sections.md) |
| Интерфейс и компоненты | [admin-ui.md](laiot/admin-ui.md) |
| Свойства, история, OnChange | [events.md](laiot/events.md) |
| Функции и обёртки | [functions.md](laiot/functions.md) |
| Уведомления | [notifications.md](laiot/notifications.md) |
| API и webhook | [api.md](laiot/api.md) |
| Кэш | [cache.md](laiot/cache.md) |
| Очередь и задания | [queue.md](laiot/queue.md) |
| CLI | [console.md](laiot/console.md) |
| Модули и SDK | [modules.md](laiot/modules.md), [sdk.md](laiot/sdk.md) |
| Фоновый runtime и systemd | [runtime.md](laiot/runtime.md) |
| База данных и миграции | [database.md](laiot/database.md) |
| Логи и диагностика | [logging.md](laiot/logging.md) |
| Тесты | [testing.md](laiot/testing.md) |
| Локальный Docker-стенд | [docker.md](laiot/docker.md) |
| База знаний | [knowledge_base.md](laiot/knowledge_base.md) |
| Комментарии в коде | [code-comments.md](laiot/code-comments.md) |
| Ограничения | [limitations.md](laiot/limitations.md) |

## Правила

- Текст на русском; технические имена остаются как есть.
- У файла — front matter (`title`, `audience`, `category`, `description`,
  `tags`, `order`).
- Ссылки относительные, пути — от корня репозитория.
- Документация меняется в том же изменении, что и код.
- Проверка: `php tests/docs_quality_smoke.php`.
