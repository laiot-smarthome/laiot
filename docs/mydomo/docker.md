---
title: MyDoMo — локальный Docker-стенд
audience: user, agent
category: dev
tags: mydomo, docker, отладка, linux, миграции
description: Назначение и запуск отладочного стенда, его внешние порты, подготовка схемы и отличия от production-runtime.
order: 16
---

# Docker для отладки MyDoMo

Compose — только локальный стенд и Linux-проверки. Он не является
production-инсталлятором и не устанавливает systemd-агент рабочего сервера.
Быстрый просмотр обычно выполняется через dev_server.php; пересборка
Docker нужна при изменении образа или проверке Linux-поведения.

## Состав и запуск

Сервисы: app с nginx/PHP-FPM, MariaDB, Redis, Mosquitto. Наружу опубликован
HTTP-порт MJD_HTTP_PORT (по умолчанию 80); БД, Redis и MQTT находятся во
внутренней compose-сети. Уровень защиты и настройки MQTT отладочные.

```bash
docker compose config --quiet
docker compose up -d --build app
docker compose ps
docker compose logs --tail=80 app
```

Команды создания/перезапуска меняют локальный стенд. Их не нужно выполнять
при каждой правке Markdown или после каждого коммита.

Параметры compose можно передать через отдельный docker/.env с
`docker compose --env-file docker/.env ...`; это не замена рабочего
приложенческого .env. Нельзя переносить локальные пароли из примеров на
сервер. Отладочные значения администратора задаются MJD_ADMIN_*.

## Подготовка базы

При первичной инициализации MariaDB seed задаёт MJD_DB_SEED, по умолчанию
none. Для MyDoMo нужен новый формат схемы, а не импорт alpha-дампа в
качестве якобы совместимой базы.

При MJD_RUN_MIGRATIONS=1 entrypoint app проверяет число таблиц:

- Для пустой БД применяет `bin/mydomo db:migrate`, затем создаёт
  администратора через user:create с MJD_ADMIN_USER/NAME/PASSWORD.
- Для непустой БД пока также вызывает старые migrations:run/configure.php
  и затем миграции MyDoMo. Это сохранившаяся особенность отладочного
  entrypoint, не новая production-архитектура.

Смотрите фактический вывод: entrypoint может записать предупреждение и
продолжить запуск после неуспешной миграции. Запущенный контейнер не
доказывает, что схема и пользователь подготовлены успешно.

Пересоздание volume удаляет тестовые данные. Оно допустимо только для
явно выбранного одноразового стенда и не должно быть автоматическим
«исправлением» любой ошибки миграции. Защита DB_PROTECT_DROP не защищает
от удаления volume средствами Docker.

## Проверки готового стенда

```bash
curl -I http://127.0.0.1/login
curl -sS http://127.0.0.1/health
curl -sS http://127.0.0.1/api/v1/health
```

Ожидания: /login — 200; /health — 200 при готовых БД/кэше либо осмысленный
503; /admin после входа — 200. Без сессии `/` показывает вход, а с сессией
переходит на /admin; отдельный редирект `/` → /login не обязателен.

Маршруты `/api/v1/*` обслуживает kernel, точный `/api` — ядровая страница.
Граница с legacy `/api/*` и текущая проблема CRUD webhook подробно описаны
в [api.md](api.md). Проверяйте путь через nginx, не только прямым вызовом
Application в PHPUnit.

## Команды внутри контейнера

```bash
docker compose exec -T app php bin/mydomo about
docker compose exec -T app php bin/mydomo doctor
docker compose exec -T app php bin/mydomo db:status
docker compose exec -T app php bin/mydomo queue:stats
```

Это команды подключённого локального стенда, не изолированный unit-набор.
Для fork/posix можно проверять PHP-расширения и запускать адресные задачи
на тестовых данных. Наличие Redis-контейнера не заменяет отдельного
сценария проверки live-lock/heartbeat.

Стенд не доказывает работу systemd: запуск внутри контейнера отличается
от [runtime.md](runtime.md). Общие проверки кода — [testing.md](testing.md).
