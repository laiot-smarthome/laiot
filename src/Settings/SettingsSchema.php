<?php

declare(strict_types=1);

namespace MajorDoMo\Settings;

use Illuminate\Support\Arr;
use MajorDoMo\Foundation\Config;
use MajorDoMo\Foundation\ConfigLoader;

/**
 * Единый каталог редактируемых параметров и их привязок к конфигурации ядра.
 *
 * 2026-10-27 — UI и bootstrap используют одни ключи, типы и значения по умолчанию.
 */
final class SettingsSchema
{
    /** @return array<string, array{title:string, description:string}> */
    public static function groups(): array
    {
        return [
            'general' => ['title' => 'Основные', 'description' => 'Время системы и диагностика ошибок.'],
            'database' => ['title' => 'База данных', 'description' => 'Подключение к MySQL / MariaDB. Перед сохранением проверяется база и доступ администратора. Данные между базами не переносятся.'],
            'cache' => ['title' => 'Кэш и Redis', 'description' => 'Общее хранилище кэша, блокировок и состояния фоновых процессов.'],
            'queue' => ['title' => 'Очередь', 'description' => 'Повторы заданий и ограничения пользовательских обработчиков.'],
            'logging' => ['title' => 'Журналы', 'description' => 'Подробность и ротация файлов журналов.'],
            'retention' => ['title' => 'Хранение данных', 'description' => 'Очистка старых записей процессом core-worker maintenance. Ноль дней отключает очистку соответствующего раздела.'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function fields(): array
    {
        return [
            'APP_TIMEZONE' => ['group' => 'general', 'path' => 'timezone', 'type' => 'select', 'label' => 'Часовой пояс', 'description' => 'Используется для времени событий, сценариев и журналов.', 'options' => array_combine(timezone_identifiers_list(), timezone_identifiers_list())],
            'APP_DEBUG' => ['group' => 'general', 'path' => 'debug', 'type' => 'bool', 'label' => 'Режим отладки', 'description' => 'Показывает подробности ошибок HTTP и включает уровень Debug в журналах. Для обычной работы выключите.'],
            'DB_HOST' => ['group' => 'database', 'path' => 'database.host', 'default' => '127.0.0.1', 'type' => 'text', 'label' => 'Сервер БД', 'description' => 'Имя хоста или IP-адрес MySQL / MariaDB.', 'required' => true],
            'DB_PORT' => ['group' => 'database', 'path' => 'database.port', 'default' => 3306, 'type' => 'int', 'min' => 1, 'max' => 65535, 'label' => 'Порт БД', 'description' => 'Обычно 3306.'],
            'DB_NAME' => ['group' => 'database', 'path' => 'database.database', 'default' => '', 'type' => 'text', 'required' => true, 'label' => 'Имя базы', 'description' => 'Уже подготовленная база MyDoMo с таблицами и действующим администратором.'],
            'DB_USER' => ['group' => 'database', 'path' => 'database.username', 'default' => '', 'type' => 'text', 'required' => true, 'label' => 'Пользователь БД', 'description' => 'Учётная запись для подключения приложения к базе.'],
            'DB_PASSWORD' => ['group' => 'database', 'path' => 'database.password', 'default' => '', 'type' => 'secret', 'label' => 'Пароль БД', 'description' => 'Пустое поле сохраняет прежний пароль. Для удаления используйте отдельный флажок.'],
            'CACHE_DRIVER' => ['group' => 'cache', 'path' => 'cache.driver', 'type' => 'select', 'label' => 'Хранилище кэша', 'description' => 'Файлы и Redis доступны всем процессам. База данных использует таблицу cache. Память процесса подходит для отладки и не объединяет фоновые процессы.', 'options' => ['file' => 'Файлы', 'redis' => 'Redis', 'database' => 'База данных', 'array' => 'Память процесса (отладка)']],
            'REDIS_HOST' => ['group' => 'cache', 'path' => 'cache.redis.host', 'type' => 'text', 'required' => true, 'label' => 'Сервер Redis', 'description' => 'Используется при выборе хранилища Redis.'],
            'REDIS_PORT' => ['group' => 'cache', 'path' => 'cache.redis.port', 'type' => 'int', 'min' => 1, 'max' => 65535, 'label' => 'Порт Redis', 'description' => 'Обычно 6379.'],
            'REDIS_DB' => ['group' => 'cache', 'path' => 'cache.redis.database', 'type' => 'int', 'min' => 0, 'max' => 2147483647, 'label' => 'База Redis', 'description' => 'Номер логической базы. Он должен существовать на сервере Redis; обычно 0.'],
            'REDIS_PASSWORD' => ['group' => 'cache', 'path' => 'cache.redis.password', 'type' => 'secret', 'label' => 'Пароль Redis', 'description' => 'Пустое поле сохраняет прежний пароль. Для удаления используйте отдельный флажок.'],
            'QUEUE_MAX_ATTEMPTS' => ['group' => 'queue', 'path' => 'queue.max_attempts', 'type' => 'int', 'min' => 1, 'max' => 100, 'label' => 'Число попыток', 'description' => 'Общее число попыток выполнения задания до переноса в журнал ошибок.'],
            'QUEUE_RETRY_DELAY' => ['group' => 'queue', 'path' => 'queue.retry_delay', 'type' => 'int', 'min' => 0, 'max' => 86400, 'label' => 'Задержка повтора, сек', 'description' => 'Пауза перед повторной попыткой после ошибки.'],
            'QUEUE_RESERVATION_TIMEOUT' => ['group' => 'queue', 'path' => 'queue.reservation_timeout', 'type' => 'int', 'min' => 1, 'max' => 86400, 'label' => 'Аренда задания, сек', 'description' => 'После этого времени незавершённое задание можно взять повторно. Должно быть больше лимита обработчика.'],
            'QUEUE_HANDLER_TIMEOUT' => ['group' => 'queue', 'path' => 'queue.handler_timeout', 'type' => 'int', 'min' => 1, 'max' => 3600, 'label' => 'Лимит обработчика, сек', 'description' => 'Время всего пользовательского обработчика, включая вложенные методы. Контроль процессов работает на Linux.'],
            'LOG_LEVEL' => ['group' => 'logging', 'path' => 'logging.level', 'type' => 'select', 'label' => 'Уровень журналирования', 'description' => 'Записываются сообщения выбранного уровня и более серьёзные. В режиме отладки всегда используется Debug.', 'options' => ['debug' => 'Debug — отладка', 'info' => 'Info — информация', 'notice' => 'Notice — замечания', 'warning' => 'Warning — предупреждения', 'error' => 'Error — ошибки', 'critical' => 'Critical — критические ошибки', 'alert' => 'Alert — аварии', 'emergency' => 'Emergency — отказ системы']],
            'LOG_MAX_FILES' => ['group' => 'logging', 'path' => 'logging.max_files', 'type' => 'int', 'min' => 1, 'max' => 3650, 'label' => 'Дней файловых журналов', 'description' => 'Число суточных файлов, сохраняемых для каждого канала журнала.'],
            'RETENTION_HISTORY_DAYS' => ['group' => 'retention', 'path' => 'retention.history_days', 'type' => 'int', 'min' => 0, 'max' => 36500, 'label' => 'История свойств, дней', 'description' => 'Срок хранения прошлых значений свойств объектов.'],
            'RETENTION_EVENTS_DAYS' => ['group' => 'retention', 'path' => 'retention.events_days', 'type' => 'int', 'min' => 0, 'max' => 36500, 'label' => 'Журнал событий, дней', 'description' => 'Срок хранения обработанных событий свойств.'],
            'RETENTION_QUEUE_DAYS' => ['group' => 'retention', 'path' => 'retention.queue_days', 'type' => 'int', 'min' => 0, 'max' => 36500, 'label' => 'Ошибки очереди, дней', 'description' => 'Срок хранения заданий, завершившихся ошибкой.'],
            'RETENTION_TIMEOUTS_DAYS' => ['group' => 'retention', 'path' => 'retention.timeouts_days', 'type' => 'int', 'min' => 0, 'max' => 36500, 'label' => 'Завершённые таймеры, дней', 'description' => 'Срок хранения исполненных и отменённых таймеров.'],
            'RETENTION_ERRORS_DAYS' => ['group' => 'retention', 'path' => 'retention.errors_days', 'type' => 'int', 'min' => 0, 'max' => 36500, 'label' => 'Системные ошибки, дней', 'description' => 'Срок хранения записей системных ошибок и их отдельных файлов.'],
            'RETENTION_BATCH' => ['group' => 'retention', 'path' => 'retention.batch', 'type' => 'int', 'min' => 1, 'max' => 100000, 'label' => 'Размер порции очистки', 'description' => 'Максимум строк за одну порцию, чтобы очистка не удерживала долгую блокировку базы.'],
            'RETENTION_INTERVAL' => ['group' => 'retention', 'path' => 'retention.interval', 'type' => 'int', 'min' => 300, 'max' => 604800, 'label' => 'Интервал очистки, сек', 'description' => 'Пауза между проходами фоновой очистки. Минимум 300 секунд.'],
        ];
    }

    /** @return array<string, mixed> */
    public static function defaults(string $root): array
    {
        $config = ConfigLoader::load($root);
        $defaults = [];
        foreach (self::fields() as $key => $field) {
            $defaults[$key] = Arr::get($config, (string)$field['path'], $field['default'] ?? '');
        }

        return $defaults;
    }

    /** @return array<string, mixed> */
    public static function environment(string $root): array
    {
        $defaults = self::defaults($root);
        $config = [];
        foreach (self::fields() as $key => $field) {
            $value = match ($field['type']) {
                'bool' => Config::bool($key, (bool)$defaults[$key]),
                'int' => Config::int($key, (int)$defaults[$key]),
                default => Config::string($key, (string)$defaults[$key]),
            };
            Arr::set($config, (string)$field['path'], $value);
        }

        return $config;
    }
}
