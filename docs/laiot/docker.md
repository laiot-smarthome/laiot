---
title: LaIOT — локальный Docker-сервер
audience: user, agent
category: dev
tags: laiot, docker, systemd, nginx, mariadb, redis
description: Единственная группа LaIOT с Ubuntu, PHP-FPM, MariaDB, Redis и системным агентом для локальной проверки приложения.
order: 26
---

# Локальный Docker-сервер

Обычный запуск из корня поднимает **одну группу `laiot`** с одним
Ubuntu-контейнером. Внутри работают настоящий systemd, nginx, PHP 8.3 FPM,
MariaDB, Redis, Mosquitto и административный `laiot-agent`. Фоновые задачи
LaIOT обслуживаются обычными systemd-службами, как в серверной установке.
Это локальный стенд, а не production-инструкция: TLS не настроен,
пароли базы простые, порт публикуется только на loopback.

## Запуск

На хосте должны быть установлены зависимости и собраны ассеты:

```bash
composer install
npm ci
npm run build
docker compose up --build -d
```

Корневой `docker-compose.yml` подключает `docker/server/compose.yml`.
Не задавайте отдельный `-p`, если не нужен намеренно второй стенд.
Стандартный адрес — `http://127.0.0.1:8080`; переменная
`LAIOT_SERVER_PORT` меняет опубликованный порт.

Первый запуск копирует текущий код в Linux-том и готовит службы.
Доступен мастер `http://127.0.0.1:8080/install`. Ключ установки:

```bash
docker compose exec server cat /srv/laiot/var/install.key
```

В мастере используйте локальные параметры: база `laiot`, пользователь
`laiot`, пароль `laiot`, адрес `127.0.0.1:3306`. Redis —
`127.0.0.1:6379`, база 0. Логин и пароль администратора задаются в мастере.
Для установки через CLI применяется штатная команда
[install](console.md#install).

После установки запускаются `queue`, `events`, `timeouts`, `maintenance`,
`system-events`, `websocket` и `supervisor`. Supervisor автоматически
поднимает worker включённых модулей (MQTT, Zigbee) и их процессы доставки,
следит за состоянием и регистрирует падения. Задачи управляются через агент;
X-Ray может запускать, останавливать и перезапускать известные процессы.

## Данные и текущий код

Приложение находится в `/srv/laiot`. Его `.env`, БД, Redis и системные
конфиги живут только в отдельных Linux-томах группы `laiot`. Рабочий `.env`
хоста не подключается. Исходники копируются в образ при сборке, затем
в том при первом запуске. Изменение файла на Windows само по себе не
меняет установленный код внутри тома. Для проверки отдельной правки
можно скопировать готовый файл через `docker compose cp` и перезапустить
затронутую службу; обновления приложения проверяются штатным обновителем.
Пересоздание контейнера сохраняет установку и выбранный релиз.

Начальная версия — `beta-0.0.0`; после первого обновления раскладку
`current` и ссылки на постоянные данные создаёт обновитель.
Системные конфиги доступны в `/etc/laiot`.

## Управление

```bash
docker compose ps
docker compose logs --tail 100 server
docker compose exec server systemctl status laiot-agent --no-pager
docker compose exec server systemctl status 'laiot-core@*' --no-pager
docker compose exec server journalctl -u laiot-core@events -n 50 --no-pager
docker compose exec server bash
```

CLI приложения выполняйте от владельца файлов:

```bash
docker compose exec --user laiot server php /srv/laiot/bin/laiot about
docker compose exec --user laiot server php /srv/laiot/bin/laiot db:status
```

Остановка и запуск с сохранением данных:

```bash
docker compose stop
docker compose start
```

Полный сброс **удаляет только установку и данные группы LaIOT**:

```bash
docker compose down --volumes
docker compose up --build -d
```

Не используйте глобальные `docker system prune` и удаление чужих томов.
Другие проекты, включая Immich и ресурсы с префиксом `immich`,
не относятся к этому стенду.

## Границы проверки

Контейнер использует собственное пространство cgroup и права SYS_ADMIN /
SYS_NICE, необходимые вложенному systemd. Redis отключает PrivateUsers
только внутри контейнера; остальные настройки службы сохранены.
Это модель сервера с ядром Docker Desktop, а не отдельная виртуальная
машина. Она позволяет проверить Unix-сокет агента, реальное управление
worker, WebSocket и конвейер обновления. Производительность Windows-
монтирования и особенности production-окружения требуют отдельной проверки.
