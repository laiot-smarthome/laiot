---
title: MyDoMo — локальный Docker-стенд
audience: user, agent
category: dev
tags: mydomo, docker, локальный стенд, mariadb, nginx, php-fpm, отладка
description: Как поднять текущую ветку MyDoMo в Docker с nginx и PHP-FPM и увидеть панель без ручной установки.
order: 26
---

# Локальный Docker-стенд

Стенд только для разработки и просмотра текущего состояния. Это не
production-развёртывание: nginx и PHP-FPM в отдельных контейнерах, MariaDB с
простыми паролями, без TLS и внешнего reverse proxy.

Стек совпадает с целевым: **nginx + PHP-FPM**, без Apache и mod_php.

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
| `web` | `nginx:1.27-alpine`, слушает `8080`, document root `public/`, PHP-запросы уходят в `app:9000` |
| `app` | `php:8.4-fpm`, пул `www` на `9000`, исходники с хоста |
| `db` | MariaDB 11, база `mydomo`, том `mydomo-db` |

Конфиг nginx — `docker/nginx/default.conf`; пул FPM —
`docker/app/php-fpm.d/zz-mydomo.conf`. `docker/app/entrypoint.sh` ждёт БД,
выполняет `php bin/mydomo db:migrate` и `user:create`, затем запускает
`php-fpm`. Переменные процесса имеют приоритет над `.env` хоста, поэтому
контейнер подключается к `db`, а не к `127.0.0.1` из локального `.env`.

## Пул PHP-FPM

Локальный пул задан явно: `pm.max_children = 10`,
`request_terminate_timeout = 60s`. Это те же ручки, что в production, но
числа меньше. `pm.max_children` — сколько HTTP-запросов одновременно
обслуживает PHP; синхронный запуск сценария занимает один процесс на всё
время выполнения. `request_terminate_timeout` — внешний потолок запроса:
сценарий с ядром не должен держать воркер бесконечно, а режим «без ядра»
дополнительно ограничен в приложении (10 секунд).

Production-пул живёт в `pool.d` на хосте (или в контейнере app), приложение
его не читает: см. [фоновый runtime](runtime.md).

## Полезные команды

```bash
docker compose logs -f web app
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
