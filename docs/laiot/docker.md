---
title: LaIOT — локальный Docker-стенд
audience: user, agent
category: dev
tags: laiot, docker, локальный стенд, mariadb, nginx, php-fpm, отладка
description: Чистый серверный стенд для мастера установки и обновлений либо отдельный режим разработки с nginx и PHP-FPM.
order: 26
---

# Локальный Docker-стенд

Стенды только для локальной разработки и проверки установки. Доступны
серверный режим с systemd и режим разработки с отдельными контейнерами.
MariaDB использует простые пароли; TLS и внешний reverse proxy не настроены.

Стек совпадает с целевым: **nginx + PHP-FPM**, без Apache и mod_php.

## Запуск

### Серверный стенд для проверки обновлений

`docker/server/compose.yml` поднимает отдельную Ubuntu 24.04 с systemd,
nginx, PHP 8.3 FPM/CLI, MariaDB, Redis, Mosquitto и штатным агентом LaIOT.
Код, `.env`, база и конфиги хранятся в собственных Linux-томах.
Это самостоятельная группа `laiot-server`, без привязки к файлам рабочего
каталога или данным других Docker-проектов.

Из корня репозитория:

```bash
docker compose -p laiot-server -f docker/server/compose.yml up --build -d
```

Открыть `http://127.0.0.1:8082/install`. Это чистая установка: администратора
нужно создать в мастере. Мастер начинается с первого шага; для подключения
доступны база `laiot`, пользователь `laiot`, локальный пароль `laiot`,
сервер `127.0.0.1:3306`; Redis —
`127.0.0.1:6379`, база 0. Доступ к установке подтверждается ключом:

```bash
docker compose -p laiot-server -f docker/server/compose.yml exec server cat /srv/laiot/var/install.key
```

После завершения мастера core-службы запускаются автоматически. Начальная
версия — `beta-0.0.0`; далее можно проверять готовые GitHub-релизы,
снимки БД, переключение `current` и восстановление после прерывания.
Проверки прав, PHP, БД и агента выполняются штатно.

Войти в терминал сервера и управлять конфигами:

```bash
docker compose -p laiot-server -f docker/server/compose.yml exec server bash
nano /srv/laiot/.env
nano /etc/laiot/nginx.conf
nano /etc/laiot/php-fpm.conf
nano /etc/laiot/php.ini
nano /etc/laiot/mariadb.cnf
systemctl reload nginx
systemctl restart php8.3-fpm
systemctl status laiot-agent 'laiot-core@*' --no-pager
journalctl -u laiot-update -n 100 --no-pager
```

CLI приложения и обновителя запускайте от владельца файлов:

```bash
docker compose -p laiot-server -f docker/server/compose.yml exec --user laiot server php /srv/laiot/bin/laiot update:status
```

Приложение находится в `/srv/laiot`, снимки — в `/srv/laiot/var/backups`,
системные конфиги — в `/etc/laiot`. После первого обновления `.env`
активного релиза ссылается на постоянный файл `/srv/laiot/.env`.
Перезапуск или пересоздание контейнера сохраняет тома и выбранный релиз;
новые версии приложения устанавливаются через обновитель. Пересборка
образа обновляет ОС и исходную заготовку для нового пустого тома.

Порт можно изменить переменной `LAIOT_SERVER_PORT`. Он публикуется только
на `127.0.0.1`. systemd использует собственный cgroup namespace и права
`SYS_ADMIN`/`SYS_NICE`; `PrivateUsers` отключён только у Redis из-за
ограничений вложенного sandbox Docker, остальные настройки службы сохранены.
Это локальная модель сервера с ядром Docker Desktop, а не отдельная VM;
она позволяет проверить настоящий конвейер обновления.

Остановить и снова запустить сервер с сохранением данных:

```bash
docker compose -p laiot-server -f docker/server/compose.yml stop
docker compose -p laiot-server -f docker/server/compose.yml start
```

Полный сброс **удаляет приложение, БД, ключи и конфиги этого стенда**:

```bash
docker compose -p laiot-server -f docker/server/compose.yml down --volumes
docker compose -p laiot-server -f docker/server/compose.yml up -d
```

### Быстрый стенд с исходниками на хосте

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
