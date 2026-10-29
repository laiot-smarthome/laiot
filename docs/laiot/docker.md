---
title: LaIOT — локальный Docker-стенд
audience: user, agent
category: dev
tags: laiot, docker, локальный стенд, mariadb, nginx, php-fpm, отладка
description: Как поднять текущую ветку LaIOT в Docker с nginx и PHP-FPM и увидеть панель без ручной установки.
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
`sysadmin` / `laiot!` (меняется переменными `LAIOT_ADMIN_*` в
`docker-compose.yml`).

Первый запуск применяет миграции и создаёт администратора; повторный запуск
с теми же томами администратора не дублирует.

## Состав

| Сервис | Что делает |
| --- | --- |
| `web` | `nginx:1.27-alpine`, слушает `8080`, document root `public/`, PHP-запросы уходят в `app:9000` |
| `app` | `php:8.4-fpm`, пул `www` на `9000`, исходники с хоста |
| `worker-events` | `php bin/laiot core-worker events` — OnChange, история и доставка изменений в `module.<id>` |
| `worker-mqtt` | `php bin/laiot worker mqtt` — приём и отправка MQTT |
| `db` | MariaDB 11, база `laiot`, том `laiot-db` |
| `init` | одноразовый сервис: миграции и администратор, после чего завершается |

`worker-events` и `worker-mqtt` работают под `www-data`, чтобы файловый кэш
и блокировки в общем томе `var/` не оставались во владении root. Оба
процесса перезапускаются Docker-ом (`restart: unless-stopped`) и стартуют
только после успешного `init`; поэтому миграции не выполняются параллельно
в нескольких контейнерах.

Конфиг nginx — `docker/nginx/default.conf`; пул FPM —
`docker/app/php-fpm.d/zz-laiot.conf`. `docker/app/entrypoint.sh` ждёт БД,
выполняет `php bin/laiot db:migrate` и `user:create`, затем запускает
команду сервиса. Переменные процесса имеют приоритет над `.env` хоста,
поэтому контейнер подключается к `db`, а не к `127.0.0.1` из локального
`.env`.

## MQTT на стенде

1. Откройте `http://127.0.0.1:8080/module/mqtt/settings`.
2. Укажите брокер. Для публичной проверки подходит
   `broker.hivemq.com:1883`; `test.mosquitto.org` из контейнера может
   принимать TCP и не отвечать на CONNECT. Это общие брокеры: не
   отправляйте туда секреты и не подписывайтесь на `#` без необходимости.
   Рабочий фильтр — уникальный, например `laiot/<случайный-суффикс>/#`.
3. Сохраните настройки: контейнер `worker-mqtt` переподключится сам в
   течение нескольких секунд.
4. Создайте топик, привяжите его к свойству или проверьте отправку кнопкой
   в карточке топика. Состояние цикла и очередь видны на `/module/mqtt`.

Логи процессов пишутся в общий том `var/log` (каналы `app`, `events`,
`queue`), а не в stdout контейнера; `docker compose logs` показывает только
запуск и падения процесса. Смотреть журнал:

```bash
docker compose exec worker-mqtt tail -f var/log/app-$(date +%F).log
docker compose exec worker-events tail -f var/log/events-$(date +%F).log
```

Ошибка подключения к брокеру также видна в развёрнутой карточке «Состояние
цикла» на странице модуля.

Проверить, что worker держит соединение, можно и снаружи: страница модуля
показывает «подключён» и время последнего сообщения. Если контейнер
перезапускается, смотрите его лог и настройки TLS/авторизации.


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
docker compose exec app php bin/laiot about
docker compose exec app php bin/laiot db:status
docker compose exec app php bin/laiot queue:stats
```

Полный сброс базы (удаляет том):

```bash
docker compose down -v
docker compose up --build -d
```

## Ограничения

- `vendor/` и `node_modules/` не собираются внутри образа: они берутся с
  хоста. На чистой машине сначала выполните `composer install` и сборку.
- Нет Redis и systemd. MQTT и события свойства запускаются сервисами
  `worker-mqtt` и `worker-events`; остальные `core-worker` и `worker <id>`
  по-прежнему запускаются вручную через `docker compose exec`.
- Образ собирает расширения `pdo_mysql`, `mbstring` и `pcntl`: worker
  использует fork и timeout задачи так же, как на Linux-стенде. `posix`
  входит в базовый образ PHP.
- Windows-монтирование медленнее нативного Linux; для проверки
  производительности используйте Linux-стенд.
- Стенд не заменяет проверку systemd, pcntl и production-nginx.
