---
title: MyDoMo — база данных и время
audience: agent, user
category: data
tags: mydomo, база, миграции, время, soft-delete, carbon
description: Новая схема, реальные правила репозиториев, исключения служебных таблиц и ограничения миграций и времени.
order: 14
---

# База данных и время

MyDoMo работает с новой lowercase snake_case схемой. Совпадающее имя
`users` или `objects` не означает совместимости колонок с alpha: старые
uppercase-поля не читаются через runtime-адаптер. Отдельный перенос данных
не заменяется подключением старого SQL-слоя.

## Соединение и доступ

`DatabaseManager` собирает Illuminate Capsule лениво, предоставляет
`connection()`, `schema()`, `transaction()` и `ping()`. Основной bootstrap
настраивает MySQL; тесты явно передают SQLite. `ping()` проверяет `SELECT 1`,
но не существование всех прикладных таблиц.

Репозитории ядра обращаются к своему домену через Query Builder/Eloquent.
Модуль использует ModuleDb для своих таблиц и публичные сервисы для
действий ядра. SQL в сценарии не становится поддерживаемым контрактом
только потому, что PHP-код технически может его исполнить.

## Карта схемы

Базовая `MajorDoMo\Database\Model` поддерживает родные casts `encrypted`,
`encrypted:array` и соседние строковые encrypted-типы через общий шифратор
приложения. Секретные атрибуты нужно добавлять в `$hidden`; колонка обычно
TEXT. Query Builder и ModuleDb автоматически их не шифруют. Контракт и
создание ключа — [encryption.md](encryption.md).

| Область | Таблицы |
| --- | --- |
| Пользователи и доступ | users, roles, permissions, user_roles, role_permissions, api_tokens |
| Модель дома | classes, objects, properties, methods, locations |
| Значения | property_values, property_history, property_events, property_subscriptions |
| Сценарии | scripts, script_categories, runtime_timeouts |
| Диагностика | action_log, system_errors, notifications |
| Задания | queue_jobs, property_executions |
| SDK | module_registry, module_migrations, shared_events |
| Входящие webhook | api_webhooks |
| Настройки и миграции | settings, migrations |

Справочник актуальных имён — миграции `database/migrations`.
`module_registry.settings` хранит JSON-значения настроек модуля по схеме
`module.json`; форма и проверка — [sdk.md](sdk.md), общая страница —
`/admin/modules/<id>/settings`.
Retention (`db:prune`, `core-worker maintenance`) физически удаляет старые
`property_history`, `property_events`, failed `queue_jobs`,
`runtime_timeouts` и `system_errors`; сроки — в `config/retention.php`.
При переносе ядровых разделов миграция 000024 переименовывает прежнюю
`module_api_webhooks`, 000025 удаляет таблицу Shoutbox, 000026
переименовывает `module_shared_events` в `shared_events`, а
`2026_10_27_000001` добавляет `classes.parent_id`,
`properties.object_id` и `methods.object_id`, `2026_10_27_000002` —
таблицу подписок модулей `property_subscriptions`,
`2026_10_27_000009` — описание класса `classes.description`, а
`2026_10_27_000010` — политику повторов `properties.only_new_values`.
Это разовые преобразования pre-release-схемы, не чтение двух схем в runtime.

## Колонки и soft delete

Для новых прикладных таблиц приняты `id`, `created_at`, `updated_at`,
`deleted_at`; timestamps и softDeletes добавляются в миграции. Индексы,
уникальность и внешние ключи нужно проверять по конкретной таблице,
а не выводить из одинаковых названий колонок.

Есть служебные исключения: `module_migrations` содержит `applied_at`,
но не стандартную тройку timestamps/soft delete. Очередь физически
удаляет ack и удалённые failed, назначения ролей могут заменяться
физическим удалением строк. Нельзя обещать универсальное восстановление
любого удаления.

Наличие `deleted_at` не вводит автоматический global scope Query Builder.
Репозитории сами добавляют whereNull; для raw Query Builder этот фильтр
не появляется. Базовая Model использует `id`, int/incrementing и timestamps,
но сама не добавляет SoftDeletes. У неё guarded=[], поэтому серверная
валидация и явные поля записи остаются обязательными.

`classes.parent_id` задаёт подкласс; `0` — корневой класс. Наследование не
ограничено глубиной, а репозиторий отклоняет цикл. `properties.object_id` и
`methods.object_id` равны `0` для классового члена и id объекта для
объектного свойства/метода. Системные имена — только латиница, цифры и `_`
(`src/Support/Identifier`): класс и объект уникальны глобально, свойство и
метод — во всей ветке класса и у объекта. Одноимённое переопределение больше
не создаётся; смена родителя класса или класса объекта проверяет конфликты
свойств и методов. Чтение по цепочке сохраняет выбор ближайшего класса для
legacy-строк.

Локация — колонка `objects.location_id`; у `classes` её нет. Один класс может
иметь объекты в разных помещениях, а дерево и карточка объекта показывают
помещение самого объекта.

При soft delete класса или объекта системное имя освобождается: архивная
строка получает служебный суффикс `~deleted~<id>`, поэтому уникальный индекс
не мешает создать сущность с тем же именем заново. История и журнал хранят
id и снимки названий, а не ссылку на архивный title.

`property_values` хранит одну пару object_id/property_id. Значения есть
только у объекта: класс без объектов не имеет значений, даже если его
свойства уже созданы. Низкоуровневый ValueRepository.write восстанавливает
пару при новой записи, сохраняя created_at. Рабочая запись идёт через
PropertyValueService с проверками, историей и OnChange:
[events.md](events.md).

## Миграции ядра

Файл `database/migrations/*.php` возвращает callable, принимающий
Illuminate Database Connection. Migrator сортирует имена, пропускает
записанные в `migrations`, назначает batch и регистрирует успешный файл.

```php
return static function (\Illuminate\Database\Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    // Проверка/изменение схемы одной законченной миграцией.
};
```

`db:status` показывает состояние, `db:migrate` применяет изменения.
У нового Migrator нет публичного rollback/down-контракта и общей
транзакции вокруг всех файлов. Ошибка после части DDL может оставить
частично изменённую схему; исправление требует анализа этого состояния,
а не обещания автоматического отката.

Не редактируйте уже применённую миграцию, рассчитывая на повтор: новая
операция требует нового файла. Для нескольких зависимых записей данных
используется явная DatabaseManager.transaction. DDL и ограничения
транзакций проверяются на рабочем драйвере, SQLite не доказывает всё
поведение MariaDB.

Миграции SDK имеют отдельный журнал module_migrations и выполняются
установщиком: [sdk.md](sdk.md). Документация не предписывает очищать рабочую
БД при расхождении схемы. Сброс только выделенного тестового стенда —
отдельное осознанное действие.

## Время

Clock возвращает CarbonImmutable и строку Y-m-d H:i:s в timezone
приложения. В тесте Clock можно заморозить CarbonImmutable::setTestNow(),
после теста заморозку обязательно убрать.

Application устанавливает date_default_timezone_set. Однако многие
репозитории/очередь/worker пока напрямую используют date/time/microtime;
одна заморозка Carbon не управляет всем runtime. Для очереди и ModuleBus
предусмотрены отдельные внедряемые часы. В новых сервисах предпочтителен
Clock, но наличие этого класса не доказывает завершённую миграцию времени.

Подключение APP_TIMEZONE через .env имеет известное расхождение со схемой
Config: [configuration.md](configuration.md). Не меняйте TIMEZONE и
APP_TIMEZONE вслепую, предполагая, что новый bootstrap их отождествляет.
