---
title: MyDoMo — тестирование
audience: agent, user
category: dev
tags: mydomo, тесты, phpunit, dev-server, docker
description: Укрупнённое тестирование MyDoMo: уровни, команды, dev-server и когда нужен Docker.
order: 5
---

# Тестирование MyDoMo

## 1. Принцип

Тесты укрупнённые, не по одной кнопке.

- unit — чистая логика: config, cache keys, authorization;
- integration — БД/HTTP/очередь: миграции, вход, CRUD, worker;
- feature — контракт: 404/405/500, CSRF, API, модуль;
- browser/E2E — только критические потоки;
- багфикс — один регрессионный тест.

Ориентир: быстрый набор — до 2–3 минут; тяжёлые MariaDB/Redis — отдельно.

## 2. Команды

```bash
php vendor/phpunit/phpunit/phpunit --testdox
composer check
php tests/docs_quality_smoke.php
```

`composer check` = lint + PHP-CS-Fixer dry-run + PHPStan + PHPUnit.

## 3. Локальный просмотр

```bash
php dev_server.php --host=127.0.0.1 --port=8080
php bin/mydomo about
php bin/mydomo health
```

`bin/mydomo health` проверяет БД и кэш; без локальной БД вернёт ошибку — это
ожидаемо.

## 4. Docker

Docker — только локальная отладка и Linux-проверки, не production.
Пересобирать после каждого коммита не нужно. Когда нужен:

```bash
docker compose up -d --build app
docker compose ps
docker compose logs --tail=80 app
```

## 5. Что не тестируем

- каждую кнопку/каждый URL;
- комбинации всех опций;
- live Redis без отдельного стенда (пока отмечаем как непроверенное).
