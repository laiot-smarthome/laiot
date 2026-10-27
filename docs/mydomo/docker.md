---
title: MyDoMo — локальный Docker-стенд
audience: user, agent
category: dev
tags: mydomo, docker, локальный стенд, mariadb, отладка
description: Как поднять текущую ветку MyDoMo в Docker и увидеть панель без ручной установки.
order: 26
---

# Локальный Docker-стенд

Стенд только для разработки и просмотра текущего состояния. Это не
production-развёртывание: Apache и PHP-FPM в одном контейнере, MariaDB с
простыми паролями, без TLS и внешнего reverse proxy.

## Запуск

Нужны Docker и уже установленные зависимости хоста (`vendor/`), потому что
исходники монтируются в контейнер:

```bash
composer install
npm ci && npm run build
docker compose up --build -d
```

Открыть `http://127.0.0.1:8080/login`. Администратор по умолчанию:
`admin` / `majordomo` (меняется переменными `MYDOMO_ADMIN_*` в
`docker-compose.yml`).

Первый запуск применяет миграции и создаёт администратора; повторный запуск
с теми же томами администратора не дублирует.

## Состав

| Сервис | Что делает |
| --- | --- |
| `app` | `php:8.4-apache`, document root `public/`, исходники с хоста |
| `db` | MariaDB 11, база `mydomo`, том `mydomo-db` |

`docker/app/entrypoint.sh` ждёт БД, выполняет `php bin/mydomo db:migrate` и
`user:create`, затем запускает Apache. Переменные процесса имеют приоритет
над `.env` хоста, поэтому контейнер подключается к `db`, а не к
`127.0.0.1` из локального `.env`.

## Полезные команды

```bash
docker compose logs -f app
docker compose exec app php bin/mydomo about
docker compose exec app php bin/mydomo db:status
docker compose exec app php bin/mydomo queue:stats
```

Полный сброс базы (удаляет том):

```bash
docker compose down -v
docker compose up --build -d
```

## Ограничения

- `vendor/` и `node_modules/` не собираются внутри образа: они берутся с
  хоста. На чистой машине сначала выполните `composer install` и сборку.
- Нет Redis, MQTT и systemd. Фоновые процессы (`core-worker`,
  `worker <id>`) запускаются вручную через `docker compose exec`.
- Windows-монтирование медленнее нативного Linux; для проверки
  производительности используйте Linux-стенд.
- Стенд не заменяет проверку systemd, pcntl и production-nginx.
