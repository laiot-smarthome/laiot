---
title: MyDoMo — фоновый runtime и обновление
audience: agent, user
category: core
tags: mydomo, systemd, worker, циклы, обновление, agent
description: Как устроены фоновые задачи, привилегированный systemd-агент и обновление MyDoMo с GitHub.
order: 13
---

# Фоновый runtime

## 1. Принцип

Нет отдельных `cycle_*.php` и произвольного кода в цикле. Модуль объявляет
задачи в `module.json`, ядро запускает **один worker-процесс на модуль**,
внутри — планировщик задач. Метод задачи делает один проход; интервалы,
timeout, рестарты и память контролирует ядро/systemd.

```json
"tasks": {
  "poll": {"method": "poll", "interval": 5, "timeout": 60}
}
```

```php
final class Module extends \MajorDoMo\Modules\Module
{
    public function poll(\MajorDoMo\Worker\BackgroundTaskContext $context): void
    {
        // один проход
    }
}
```

## 2. Worker

```bash
php bin/mydomo worker <module>
php bin/mydomo worker <module> --once
```

- один процесс на модуль;
- задачи выполняются последовательно;
- на каждую задачу — fork + timeout (если доступен `pcntl`);
- heartbeat и next_run — в Redis (`worker.task.*`, `worker.lock.*`);
- `SIGTERM`/`SIGINT` — корректный выход;
- `--once` — один проход для отладки/тестов.

Redis для фонового runtime считается обязательным в production: lock,
heartbeat и расписание не должны зависеть от MySQL. В тестах допустим
array-драйвер, но он не переживает процесс.

### Критичные ядровые процессы

Очередь и `setTimeOut` не делят процесс с модульными задачами:

```bash
php bin/mydomo core-worker queue
php bin/mydomo core-worker timeouts
```

Они запускаются отдельными юнитами `mydomo-core@queue`/`mydomo-core@timeouts`
с `Nice=-5`, `CPUWeight=500` и своим `MemoryMax`. Это гарантирует, что
тяжёлая задача модуля не задержит обработку jobs/таймеров.

OnChange-обработчики пока выполняются в `PropertyValueService` синхронно.
Следующий шаг — outbox/queue для handler-вызовов и отдельный
`mydomo-core@events`, чтобы сохранить порядок по свойству и не занимать
HTTP-запрос. Это отдельное изменение семантики, его нельзя делать вслепую.

Память ограничивается unit-файлом (`MemoryMax=256M`), а не самим PHP-кодом.

## 3. systemd-агент

PHP-FPM работает под непривилегированным пользователем и **не пишет** в
`/etc/systemd`. Для управления нужен отдельный root-сервис `mydomo-agent`
с Unix-сокетом:

```text
/run/mydomo/agent.sock  0660 mydomo
```

PHP отправляет только:

```json
{"action": "worker.restart", "module": "weather"}
```

или `workers.sync` со списком включённых модулей. Агент сам проверяет id и
вызывает `systemctl start|stop|restart|show mydomo-worker@<module>`.
Никаких shell-строк, путей и конфигов от PHP.

### Установка

1. `deploy/systemd/mydomo-worker@.service` — шаблон, один на все модули;
   агент подставляет `root`, `user`, `group` и кладёт в
   `/etc/systemd/system`.
2. `deploy/systemd/mydomo-agent.service` — root-сервис агента; путь
   `@@ROOT@@` заменяется при установке.
3. `/etc/mydomo/agent.json`:

```json
{
  "root": "/srv/mydomo",
  "user": "mydomo",
  "group": "mydomo",
  "socket": "/run/mydomo/agent.sock",
  "systemctl": "/usr/bin/systemctl",
  "unit_dir": "/etc/systemd/system",
  "allowed_uid": 1000
}
```

4. `php bin/mydomo-agent install` — установить worker-шаблон.
5. `systemctl enable --now mydomo-agent`.

Агент не запускает произвольные команды. `worker.install` — единственная
административная операция, остальное — start/stop/restart/status/sync.

## 4. Обновление

Решение: обновление **только вручную**, по кнопке. Любой коммит в GitHub
считается доступной версией; публичный репозиторий
`SmoKE585/majordomo`; без GitHub Actions; сборка — на сервере пользователя.

План:

1. Фоновая проверка `commits/<branch>` раз в несколько часов; в админке —
   «Доступно обновление» и кнопка «Проверить сейчас».
2. Пользователь нажимает «Обновить».
3. Скачивается архив коммита codeload GitHub.
4. Новый каталог `releases/<sha>`, сборка на сервере:
   `composer install --no-dev`, `npm ci`, `npm run build` — с таймаутами.
5. `db:migrate`, атомарное переключение `current`, рестарт воркеров через
   агент.
6. Health-check; при ошибке — откат на предыдущий release.
7. Старый release удаляется после успешной проверки.

Автоматического применения нет. Это важно: любой коммит может быть
сломанным, поэтому кнопка и откат обязательны.

## 5. Ограничения

- systemd-агент реализован для Linux; на Windows/Docker без systemd
  доступен только `worker --once` и ручной запуск.
- supervisor-адаптер пока не реализован; systemd — основной backend.
- Обновление ещё не подключено к UI; сначала worker/agent, затем UpdateService.
