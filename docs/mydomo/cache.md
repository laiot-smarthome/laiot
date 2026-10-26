---
title: MyDoMo — кэш и Redis
audience: user, agent
category: core
tags: mydomo, кэш, redis, cache, ttl, worker
description: Драйверы и методы CacheStore, область очистки и зависимость фоновых процессов от Redis.
order: 5
---

# Кэш MyDoMo

Приложение получает `CacheStore` через контейнер или `$app->cache()`.
`CacheFactory` собирает Illuminate Cache Repository; драйвер по умолчанию
`file`, выбор рабочего драйвера — `CACHE_DRIVER`.

## Драйверы

| Драйвер | Хранилище | Условия |
| --- | --- | --- |
| `array` | Память текущего процесса | Тесты, не общий lock между процессами |
| `file` | `var/cache/data` в рабочем bootstrap | Отдельный доступный для записи каталог |
| `database` | Таблица `cache`, если не передано другое имя | Соединение и схема таблицы должны быть подготовлены |
| `redis` | Redis через Predis | `REDIS_HOST/PORT/DB/PASSWORD`, доступный Redis |

Автоматического переключения на другой драйвер при ошибке нет. Параметры
подключения и границы `.env` — в [configuration.md](configuration.md).
Включение `database` само по себе не создаёт таблицу кэша; наличие нужной
миграции нужно проверить, не направляя приложение в случайную рабочую БД.

## Методы и срок жизни

```php
$cache = $app->cache();
$cache->put('weather.current', ['temperature' => 20], 300);
$value = $cache->get('weather.current', null);
$cache->add('weather.refreshing', true, 30);
$cache->has('weather.current');
$cache->forget('weather.current');
```

TTL принимает секунды, `DateInterval` или `null` для бессрочной записи.
Нулевой/отрицательный целочисленный TTL не создаёт бессрочное значение:
он следует семантике удаления Illuminate Cache.

| Метод | Что делает |
| --- | --- |
| `get($key, $default = null)` | Читает значение или значение по умолчанию |
| `put($key, $value, $ttl = null)` | Записывает, возвращает bool |
| `add($key, $value, $ttl = null)` | Записывает только отсутствующий ключ |
| `remember($key, $ttl, Closure)` | Вычисляет и записывает промах |
| `rememberForever($key, Closure)` | То же без истечения |
| `pull($key, $default = null)` | Читает и удаляет |
| `increment/decrement($key, $value = 1)` | Меняет счётчик, возвращает int или bool |

Атомарность и поведение счётчиков зависят от драйвера. `remember` не
гарантирует единственный запуск вычисления между конкурентными процессами.

## Ключи и очистка

Ключ — 1–180 символов `[A-Za-z0-9_.-]`. Перед обращением к хранилищу
добавляется `mydomo.`. Двоеточия, пробелы и русские буквы в ключе
`CacheStore` не допускаются; пользовательские названия сначала кодируются.

`php bin/mydomo cache:clear` вызывает `CacheStore::clear()` и меняет
состояние выбранного хранилища:

- Redis: обход `SCAN` и удаление ключей с прикладным префиксом.
- Array/file/database: передача `clear()` нижележащему Repository.
  Очистка охватывает его хранилище, а не только ключи текущего префикса.
  Для этих драйверов нужен выделенный каталог/таблица/экземпляр хранилища.

Отдельного публичного `clearPrefix()` в `CacheStore` нет. Очистка не
подменяет сброс Config или перекомпиляцию Blade.

## Фоновое состояние

`BackgroundTaskState` использует этот же CacheStore: worker-lock, heartbeat
и расписание — ключи `worker.*` с общим префиксом. Production-runtime
проектируется с Redis; замена драйвера на `array` делает состояние локальным
одному процессу. Очистка кэша затрагивает и эти ключи, поэтому её нельзя
считать безвредной операцией для работающих worker-процессов.

Хранилище самих заданий очереди остаётся в БД: Redis не заменяет
`queue_jobs`. Подробности — [queue.md](queue.md) и [runtime.md](runtime.md).
Live-проверка Redis и Linux/systemd отмечаются отдельно от тестов на array.
