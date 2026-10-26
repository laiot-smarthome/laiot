---
title: MyDoMo — база данных и время
audience: agent, user
category: core
tags: mydomo, база, таблицы, миграции, carbon, время, soft-delete
description: Правила новых таблиц MyDoMo — lowercase snake_case, timestamps, soft deletes, Carbon/Clock и миграции.
order: 7
---

# База данных и время MyDoMo

## 1. Имена

Новые таблицы и колонки — **только lowercase snake_case**:

```text
queue_jobs
module_shared_events
api_tokens
user_roles
```

Никаких `ID`, `QUEUE`, `CREATED_AT`. Старые таблицы alpha (`users`,
`settings`, `objects`) сохраняют uppercase-колонки как legacy-исключение;
новый код не копирует этот стиль.

## 2. Стандартные колонки

Каждая новая таблица имеет:

```text
id           bigint unsigned / increments
created_at   datetime
updated_at   datetime
deleted_at   datetime null
```

- `id` — первичный ключ;
- `created_at`/`updated_at` — через `$table->timestamps()`;
- `deleted_at` — через `$table->softDeletes()`;
- выборки по умолчанию исключают `deleted_at is not null`;
- физическое удаление — только для пивот-таблиц/очереди, если это осознанно.

Пивот-таблицы (`user_roles`, `role_permissions`) тоже получают `id`,
timestamps и `deleted_at`.

## 3. Carbon и Clock

Время — только через `MajorDoMo\Support\Clock`:

```php
$clock = $app->clock();
$clock->now();        // CarbonImmutable
$clock->nowString();  // Y-m-d H:i:s
$clock->timezone();   // Europe/Moscow
```

- часовой пояс — `APP_TIMEZONE`, по умолчанию `Europe/Moscow`;
- `Application::boot()` вызывает `date_default_timezone_set()`;
- в тестах время замораживается `CarbonImmutable::setTestNow()`;
- не использовать `date()`/`time()` в новом коде, кроме низкоуровневых
  адаптеров, где Clock ещё не прокинут.

## 4. Миграции

- файлы — `database/migrations/*.php`;
- файл возвращает `callable(Connection)`;
- журнал — таблица `migrations` (`id`, `migration`, `batch`,
  `created_at`, `updated_at`, `deleted_at`);
- применённые миграции не выполняются повторно;
- для новой таблицы сразу задаются timestamps и soft deletes;
- **pre-release:** имена уже созданных тестовых таблиц менялись, поэтому
  локальную dev-БД нужно пересоздать; production-данных MyDoMo ещё нет.

Пример:

```php
$schema->create('notes', static function (Blueprint $table): void {
    $table->increments('id');
    $table->string('title');
    $table->text('body')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

## 5. Legacy

`alpha`-таблицы и их uppercase-колонки не переименовываются в этом этапе.
Если нужна совместимость, создаётся адаптер/репозиторий, а не копирование
стиля в новую схему.
