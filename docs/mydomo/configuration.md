---
title: MyDoMo — конфигурация и окружение
audience: user, agent
category: core
tags: mydomo, конфигурация, env, redis, база, debug
description: Как новое ядро получает настройки, какие ключи действительно подключены и где заканчивается действие .env.
order: 3
---

# Конфигурация MyDoMo

Главный источник настроек — `MajorDoMo\Foundation\Config`. Веб и основной
CLI входят через `bootstrap/app.php`: он вызывает `Config::boot($root)` и
передаёт настройки в `Foundation\Application`. Полный Laravel здесь не
используется; наличие ключа Laravel в `.env` само по себе ничего не включает.

## Порядок разрешения значений

1. Переменная процесса с тем же именем.
2. `.env` в корне установки — простые `KEY=VALUE`, комментарии `#`/`;`,
   необязательные кавычки и префикс `export `.
3. Значение по умолчанию в `bootstrap/app.php` или профильном конфиге.

Подстановка `${VAR}`, `.env.local`, константы и alpha-схема не
поддерживаются. Неизвестные ключи просто игнорируются. Тип задаёт вызывающий
код: `Config::string/int/bool`, поэтому `APP_DEBUG=0` — это `false`, а не
«непустая строка».

```php
use MajorDoMo\Foundation\Config;

$debug = Config::bool('APP_DEBUG', false);
$port = Config::int('REDIS_PORT', 6379);
```

## Настройки, подключённые к новому bootstrap

| Ключ | Применение | Значение при отсутствии |
| --- | --- | --- |
| `APP_DEBUG` | Подробности HTTP-ошибок и уровень логирования | `false` |
| `DB_HOST` | Адрес MariaDB/MySQL | `localhost` в схеме; резерв bootstrap — `127.0.0.1` |
| `DB_PORT` | Порт БД | `3306`, в том числе при пустом/нулевом значении |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD` | База и учётные данные | Требуют настройки для данной установки |
| `CACHE_DRIVER` | `array`, `file`, `database`, `redis` | `file` |
| `REDIS_HOST` | Адрес Redis | `127.0.0.1` |
| `REDIS_PORT` | Порт Redis | `6379` |
| `REDIS_DB` | Номер базы Redis | `0` |
| `REDIS_PASSWORD` | Пароль Redis | Пустая строка |

Пароли задаются в окружении установки, не в примерах и не в репозитории.
Текущий bootstrap создаёт основное соединение с драйвером `mysql`:
`DB_CONNECTION=sqlite` не переключает рабочее приложение. SQLite передаётся
непосредственно в `Application` в изолированных тестах.

## Что настраивается PHP-конфигурацией

`ConfigLoader` загружает `config/app.php`, `cache.php`, `queue.php`,
`logging.php`, `retention.php`, `providers.php`; переданный конструктору
Application массив рекурсивно перекрывает их. Исключение — providers: явно
переданный список заменяет стандартный целиком. Это позволяет однозначно
задавать порядок провайдеров в изолированной сборке. Контракт —
[providers.md](providers.md).

- `views`, `view_cache`, `log` — пути и журнал из конфигурации приложения.
- `queue.table/max_attempts/retry_delay/reservation_timeout/events_lanes` —
  параметры очереди из `config/queue.php`, а не произвольные `QUEUE_*` из
  окружения. `events_lanes` задаёт число полос очереди событий.
- `cache.path` — каталог файлового кэша; `cache.table` нужен драйверу БД.
- `logging.directory/max_files/level/error_directory` — каналы и файлы ошибок
  из `config/logging.php`; `log` перекрывает путь (поток `php://stderr` в
  тестах и CLI), `debug` включает уровень Debug. Каналы пишут отдельные
  файлы с суточной ротацией.
- `retention.history_days/events_days/queue_days/timeouts_days/errors_days` —
  сроки хранения; `retention.batch` — размер порции, `retention.interval` —
  период `core-worker maintenance` в секундах (минимум 300). `0` в сроке
  отключает раздел. Подробности — [logging.md](logging.md).

## Часовой пояс

`Application` принимает ключ `timezone`, устанавливает PHP timezone и
создаёт `Clock`. `bootstrap/app.php` читает `APP_TIMEZONE` и по умолчанию
берёт `Europe/Moscow`. Поведение времени описано в [database.md](database.md).

## Перезапуск

`Config::boot()` выполняется один раз в PHP-процессе и читает `.env` при
старте. Долгоживущие worker-процессы нужно перезапускать после изменения
`.env`; отдельного кэша конфигурации нет. Очистка `cache:clear` относится к
прикладному `CacheStore`, а не к конфигурации или Blade-кэшу. Её область
действия — в [cache.md](cache.md).

## Отдельная конфигурация systemd-агента

`bin/mydomo-agent` не подключает `bootstrap/app.php`. Он читает JSON
`/etc/mydomo/agent.json`; путь можно заменить переменной процесса
`MYDOMO_AGENT_CONFIG`. Это отдельный привилегированный процесс, его
настройки не следует смешивать с конфигурацией веб-приложения.
Поля и границы доступа — в [runtime.md](runtime.md).

Проверки окружения и команды, меняющие состояние, перечислены в
[console.md](console.md); тестовое окружение — в [testing.md](testing.md).
