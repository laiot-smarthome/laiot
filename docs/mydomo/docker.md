---
title: MyDoMo — Docker-стенд
audience: user, agent
category: deploy
tags: mydomo, docker, отладка, health, миграции
description: Как поднимать и проверять Docker-стенд MyDoMo, не превращая его в production.
order: 12
---

# Docker-стенд MyDoMo

Docker — **только локальная отладка и Linux-проверки**, не production.
Основной быстрый цикл — `php dev_server.php`; Docker пересобирается по
отдельной просьбе или когда нужна проверка в Linux.

## 1. Сборка и запуск

```bash
docker compose up -d --build app
docker compose ps
docker compose logs --tail=80 app
```

Стенд поднимает nginx, PHP-FPM, MariaDB, Redis и Mosquitto. Наружу открыт
только HTTP-порт.

## 2. Проверки

```bash
curl -I http://127.0.0.1/
curl -sS http://127.0.0.1/health
curl -I http://127.0.0.1/login
```

Ожидается:

- `/` → новый вход `/login` (или `/yoyo-demo` при сессии);
- `/health` → 200 и `database: ok`, `cache: ok`;
- `/login` → 200;
- `/status` → 200 после входа.

## 3. CLI внутри контейнера

```bash
docker compose exec -T app php bin/mydomo about
docker compose exec -T app php bin/mydomo doctor
docker compose exec -T app php bin/mydomo db:status
docker compose exec -T app php bin/mydomo queue:stats
```

## 4. Схема БД

Миграции MyDoMo — pre-release. Если тестовые таблицы уже создавались со
старой схемой, стенд нужно пересоздать:

```bash
docker compose down -v
docker compose up -d --build app
docker compose exec -T app php bin/mydomo db:migrate
```

`down -v` удаляет тестовые данные; production-данных MyDoMo ещё нет.

## 5. Ограничения

- MQTT анонимный, база защищена только приложением (`DB_PROTECT_DROP=1`);
- не использовать этот стенд как инструкцию по production;
- Docker не заменяет `composer check`, `docs_quality` и PHPUnit.
