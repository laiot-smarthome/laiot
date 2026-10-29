<?php

declare(strict_types=1);

namespace LaIOT\Services\Settings;

use Illuminate\Support\Arr;
use LaIOT\Foundation\Config;
use LaIOT\Foundation\ConfigLoader;

/**
 * Единый каталог редактируемых параметров и их привязок к конфигурации ядра.
 *
 * 2026-10-29 — параметры WebSocket.
 * 2026-10-29 — минимальная важность say задаётся в общем конфиге и UI.
 * 2026-10-29 — окно повторов уведомлений настраивается через общий конфиг и UI.
 * 2026-10-28 — ключи шифрования читаются из окружения, не редактируются в UI.
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
            'notifications' => ['title' => 'Уведомления', 'description' => 'Подавление повторов и минимальная важность сообщений для внешней доставки.'],
            'websocket' => ['title' => 'Вебсокет', 'description' => 'Сервер только для исходящих обновлений свойств и системных событий. После изменения параметров перезапустите процесс websocket.'],
            'retention' => ['title' => 'Хранение данных', 'description' => 'Очистка старых записей процессом core-worker maintenance. Ноль дней отключает очистку соответствующего раздела.'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function fields(): array
    {
        return [
            'NOTIFICATIONS_DUPLICATE_WINDOW' => ['group' => 'notifications', 'path' => 'notifications.duplicate_window', 'type' => 'int', 'min' => 0, 'max' => 31536000, 'label' => 'Окно повторов, сек', 'description' => 'Повтор того же уведомления игнорируется в течение этого времени после последней сохранённой записи, даже если она прочитана. По умолчанию 3600 секунд; 0 отключает подавление.'],
            'SAY_MINIMUM_IMPORTANCE' => ['group' => 'notifications', 'path' => 'notifications.minimum_importance', 'type' => 'int', 'min' => 0, 'max' => 10, 'label' => 'Минимальная важность say', 'description' => 'Сообщения say ниже этого уровня сохраняются только в журнале. При важности от этого уровня включительно ставится фоновая доставка подписчикам. Шкала 0–10, по умолчанию 5.'],
            'WEBSOCKET_ENABLED' => ['group' => 'websocket', 'path' => 'websocket.enabled', 'type' => 'bool', 'label' => 'Вебсокет включён', 'description' => 'Разрешает выдачу тикетов и запуск сервера websocket. Выключение не ломает панель: она просто не получает обновления.'],
            'WEBSOCKET_HOST' => ['group' => 'websocket', 'path' => 'websocket.host', 'type' => 'text', 'required' => true, 'label' => 'Адрес прослушивания', 'description' => 'Интерфейс Workerman. Для nginx-прокси оставьте 127.0.0.1, чтобы порт не был доступен напрямую.'],
            'WEBSOCKET_PORT' => ['group' => 'websocket', 'path' => 'websocket.port', 'type' => 'int', 'min' => 1, 'max' => 65535, 'label' => 'Порт', 'description' => 'Порт WebSocket-сервера. Nginx проксирует /websocket на этот адрес.'],
            'WEBSOCKET_PATH' => ['group' => 'websocket', 'path' => 'websocket.path', 'type' => 'text', 'required' => true, 'label' => 'Путь', 'description' => 'Путь WebSocket-рукопожатия, по умолчанию /websocket. Должен совпадать с location в nginx.'],
            'WEBSOCKET_PUBLIC_URL' => ['group' => 'websocket', 'path' => 'websocket.public_url', 'type' => 'text', 'label' => 'Публичный адрес', 'description' => 'Полный ws:// или wss:// адрес для браузера. Пусто — тот же хост и протокол, что у панели, плюс путь (рассчитано на прокси).'],
            'WEBSOCKET_WORKERS' => ['group' => 'websocket', 'path' => 'websocket.workers', 'type' => 'int', 'min' => 1, 'max' => 64, 'label' => 'Процессов', 'description' => 'Число процессов Workerman на Linux. На Windows всегда один. Больше процессов — больше параллельных подключений, но каждый читает общий outbox.'],
            'WEBSOCKET_TICKET_TTL' => ['group' => 'websocket', 'path' => 'websocket.ticket_ttl', 'type' => 'int', 'min' => 30, 'max' => 86400, 'label' => 'Срок тикета, сек', 'description' => 'Сколько действует разрешение на подключение. Тикет выдаётся после проверки сессии и привязан к отпечатку пароля.'],
            'WEBSOCKET_MAX_CONNECTIONS' => ['group' => 'websocket', 'path' => 'websocket.max_connections', 'type' => 'int', 'min' => 1, 'max' => 100000, 'label' => 'Подключений на процесс', 'description' => 'После лимита новые подключения получают отказ. Общий предел — это значение, умноженное на число процессов.'],
            'WEBSOCKET_ROOT_DOMAIN' => ['group' => 'websocket', 'path' => 'websocket.root_domain', 'type' => 'text', 'label' => 'Корневой домен', 'description' => 'Разрешает Origin этого домена и всех его поддоменов. Пусто — взять хост из публичного адреса, BASE_URL или Host подключения.'],
            'WEBSOCKET_ALLOWED_ORIGINS' => ['group' => 'websocket', 'path' => 'websocket.allowed_origins', 'type' => 'text', 'label' => 'Точные Origin', 'description' => 'Дополнительный список точных origin через запятую, например https://home.example. Если задан, заменяет правило корневого домена. Пусто — разрешены корневой домен и его поддомены; тикет всё равно обязателен.'],
            'APP_TIMEZONE' => ['group' => 'general', 'path' => 'timezone', 'type' => 'select', 'label' => 'Часовой пояс', 'description' => 'Используется для времени событий, сценариев и журналов.', 'options' => array_combine(timezone_identifiers_list(), timezone_identifiers_list())],
            'APP_DEBUG' => ['group' => 'general', 'path' => 'debug', 'type' => 'bool', 'label' => 'Режим отладки', 'description' => 'Показывает подробности ошибок HTTP и включает уровень Debug в журналах. Для обычной работы выключите.'],
            'DB_HOST' => ['group' => 'database', 'path' => 'database.host', 'default' => '127.0.0.1', 'type' => 'text', 'label' => 'Сервер БД', 'description' => 'Имя хоста или IP-адрес MySQL / MariaDB.', 'required' => true],
            'DB_PORT' => ['group' => 'database', 'path' => 'database.port', 'default' => 3306, 'type' => 'int', 'min' => 1, 'max' => 65535, 'label' => 'Порт БД', 'description' => 'Обычно 3306.'],
            'DB_NAME' => ['group' => 'database', 'path' => 'database.database', 'default' => '', 'type' => 'text', 'required' => true, 'label' => 'Имя базы', 'description' => 'Уже подготовленная база LaIOT с таблицами и действующим администратором.'],
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
            'RETENTION_WEBSOCKET_EVENTS_DAYS' => ['group' => 'retention', 'path' => 'retention.websocket_events_days', 'type' => 'int', 'min' => 0, 'max' => 36500, 'label' => 'События WebSocket, дней', 'description' => 'Сколько хранить outbox событий WebSocket. Сервер читает только новые строки, поэтому глубокий архив не нужен.'],
            'RETENTION_WEBSOCKET_TICKET_DAYS' => ['group' => 'retention', 'path' => 'retention.websocket_ticket_days', 'type' => 'int', 'min' => 0, 'max' => 36500, 'label' => 'Тикеты WebSocket, дней', 'description' => 'Срок хранения выпущенных тикетов после создания. Активные подключения дополнительно проверяются по пользователю и отпечатку пароля.'],
            'RETENTION_BATCH' => ['group' => 'retention', 'path' => 'retention.batch', 'type' => 'int', 'min' => 1, 'max' => 100000, 'label' => 'Размер порции очистки', 'description' => 'Максимум строк за одну порцию, чтобы очистка не удерживала долгую блокировку базы.'],
            'RETENTION_INTERVAL' => ['group' => 'retention', 'path' => 'retention.interval', 'type' => 'int', 'min' => 300, 'max' => 604800, 'label' => 'Интервал очистки, сек', 'description' => 'Пауза между проходами фоновой очистки. Минимум 300 секунд.'],
        ];
    }

    /** @return array<string, mixed> */
    public static function defaults(string $root): array
    {
        $config = ConfigLoader::load($root);
        $defaults = [];
        foreach (self::environmentFields() as $key => $field) {
            $defaults[$key] = Arr::get($config, (string)$field['path'], $field['default'] ?? '');
        }

        return $defaults;
    }

    /** @return array<string, mixed> */
    public static function environment(string $root): array
    {
        $defaults = self::defaults($root);
        $config = [];
        foreach (self::environmentFields() as $key => $field) {
            $value = match ($field['type']) {
                'csv' => array_values(array_filter(
                    array_map('trim', explode(',', Config::string($key))),
                    static fn (string $value): bool => $value !== ''
                )),
                'bool' => Config::bool($key, (bool)$defaults[$key]),
                'int' => Config::int($key, (int)$defaults[$key]),
                default => Config::string($key, (string)$defaults[$key]),
            };
            Arr::set($config, (string)$field['path'], $value);
        }

        return $config;
    }

    /** @return array<string, array<string, mixed>> */
    public static function environmentFields(): array
    {
        // Ключ создаёт установщик. Общая форма настроек не должна позволять
        // случайно заменить его и потерять доступ к зашифрованным данным.
        return self::fields() + [
            'BASE_URL' => ['path' => 'base_url', 'type' => 'text'],
            'APP_KEY' => ['path' => 'encryption.key', 'type' => 'secret'],
            'APP_PREVIOUS_KEYS' => ['path' => 'encryption.previous_keys', 'type' => 'csv'],
        ];
    }
}
