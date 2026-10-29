<?php

declare(strict_types=1);

namespace LaIOT\Mqtt;

use LaIOT\Services\Cache\CacheStore;
use LaIOT\Services\Modules\ModuleDb;
use LaIOT\Services\Modules\ModuleSettings;

/**
 * Параметры подключения и поведения MQTT.
 *
 * Core-модуль не имеет строки настроек в `module_registry` (общий экран
 * настроек SDK для core закрыт), поэтому параметры хранятся в собственной
 * таблице `module_mqtt_settings`. Схема и приведение типов — те же, что у
 * SDK: `ModuleSettings::cast()` не даёт сохранить мусор.
 *
 * Значения кешируются в памяти процесса; долгоживущий worker сверяет версию
 * в CacheStore и переподключается после сохранения формы.
 *
 * 2026-10-27 — настройки MQTT для core-модуля.
 */
final class Settings
{
    public const CACHE_VERSION = 'mqtt.settings_version';

    /** @var array<string, array{title:string,type:string,default:mixed,options:array<string,string>,description:string}> */
    private const SCHEMA = [
        'client_id' => [
            'title' => 'Имя клиента',
            'type' => 'string',
            'default' => 'LaIOT MQTT',
            'options' => [],
            'description' => 'Префикс client id. К нему добавляется PID процесса: два клиента с одним id выключают друг друга.',
        ],
        'host' => [
            'title' => 'Хост брокера',
            'type' => 'string',
            'default' => 'localhost',
            'options' => [],
            'description' => 'IP-адрес или DNS-имя MQTT-брокера.',
        ],
        'port' => [
            'title' => 'Порт',
            'type' => 'int',
            'default' => 1883,
            'options' => [],
            'description' => 'Обычно 1883, для TLS — 8883.',
        ],
        'auth' => [
            'title' => 'Авторизация',
            'type' => 'bool',
            'default' => false,
            'options' => [],
            'description' => 'Передавать логин и пароль при подключении.',
        ],
        'username' => [
            'title' => 'Логин',
            'type' => 'string',
            'default' => '',
            'options' => [],
            'description' => 'Логин брокера; используется при включённой авторизации.',
        ],
        'password' => [
            'title' => 'Пароль',
            'type' => 'string',
            'default' => '',
            'options' => [],
            'description' => 'Пароль брокера; хранится в таблице модуля и доступен только администратору.',
        ],
        'tls' => [
            'title' => 'TLS',
            'type' => 'bool',
            'default' => false,
            'options' => [],
            'description' => 'Шифровать соединение TLS.',
        ],
        'tls_ca' => [
            'title' => 'CA-сертификат',
            'type' => 'string',
            'default' => '',
            'options' => [],
            'description' => 'Путь к PEM-файлу CA на сервере. Пусто — системные корневые сертификаты.',
        ],
        'tls_verify' => [
            'title' => 'Проверять сертификат',
            'type' => 'bool',
            'default' => true,
            'options' => [],
            'description' => 'Отключайте только для самоподписанного сертификата в локальной сети.',
        ],
        'query' => [
            'title' => 'Подписки',
            'type' => 'text',
            'default' => '/var/now/#',
            'options' => [],
            'description' => 'Список MQTT-фильтров через запятую или с новой строки, например /var/now/#, home/+/state.',
        ],
        'keepalive' => [
            'title' => 'Keepalive, сек',
            'type' => 'int',
            'default' => 10,
            'options' => [],
            'description' => 'Интервал ping брокеру; слишком большой скрывает обрыв связи.',
        ],
        'stripmode' => [
            'title' => 'Только привязанные топики',
            'type' => 'bool',
            'default' => false,
            'options' => [],
            'description' => 'Не записывать значения топиков без привязки и без привязанных дочерних путей.',
        ],
        'unlinked_interval' => [
            'title' => 'Интервал несвязанных топиков, сек',
            'type' => 'int',
            'default' => 3600,
            'options' => [],
            'description' => 'Как часто обновлять последнее значение топика без привязки. 0 — каждое сообщение.',
        ],
        'dedup_seconds' => [
            'title' => 'Подавление повторов, сек',
            'type' => 'int',
            'default' => 5,
            'options' => [],
            'description' => 'Игнорировать одинаковый payload топика в течение интервала. 0 — не подавлять.',
        ],
        'history_keep_days' => [
            'title' => 'Хранить историю, дней',
            'type' => 'int',
            'default' => 30,
            'options' => [],
            'description' => 'Срок хранения журнала входящих и исходящих сообщений.',
        ],
        'debug' => [
            'title' => 'Отладочный журнал',
            'type' => 'bool',
            'default' => false,
            'options' => [],
            'description' => 'Писать входящие и исходящие сообщения в журнал приложения.',
        ],
    ];

    /** @var array<string, mixed> */
    private array $values = [];

    private string $loadedVersion = '';

    private float $checkedAt = 0.0;

    public function __construct(
        private readonly Schema $schema,
        private readonly ModuleDb $moduleDb,
        private readonly CacheStore $cache
    ) {
    }

    /**
     * @return array<string, array{title:string,type:string,default:mixed,options:array<string,string>,description:string}>
     */
    public static function schema(): array
    {
        return self::SCHEMA;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureLoaded();

        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $this->ensureLoaded();

        return $this->values;
    }

    public function string(string $key): string
    {
        return (string)$this->get($key, '');
    }

    public function int(string $key): int
    {
        return (int)$this->get($key, 0);
    }

    public function bool(string $key): bool
    {
        return (bool)$this->get($key, false);
    }

    /**
     * Проверенные фильтры подписки без пустых строк.
     *
     * @return list<string>
     */
    public function queries(): array
    {
        $raw = (string)$this->get('query', '');
        $items = preg_split('/[\r\n,]+/', $raw) ?: [];
        $filters = [];
        foreach ($items as $item) {
            $item = trim($item);
            if ($item !== '' && !in_array($item, $filters, true)) {
                $filters[] = $item;
            }
        }

        return $filters;
    }

    public function version(): string
    {
        return (string)($this->cache->get(self::CACHE_VERSION) ?? '0');
    }

    /**
     * Перечитывает настройки, если версия в кэше изменилась.
     *
     * Worker вызывает это раз в несколько секунд: сохранение формы
     * переподключает его к новому брокеру без рестарта процесса.
     */
    public function refresh(float $maxAgeSeconds = 5.0): bool
    {
        $now = microtime(true);
        if ($this->checkedAt > 0 && $now - $this->checkedAt < $maxAgeSeconds) {
            return false;
        }
        $this->checkedAt = $now;
        $version = $this->version();
        if ($this->values === [] || $version !== $this->loadedVersion) {
            $this->load();

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function save(array $input): void
    {
        $values = ModuleSettings::cast(self::SCHEMA, $input);
        $this->schema->ensure();
        foreach ($values as $name => $value) {
            $stored = is_bool($value) ? ($value ? '1' : '0') : (string)$value;
            $query = $this->moduleDb->table('settings')->where('name', $name);
            if ($query->first() === null) {
                $this->moduleDb->table('settings')->insert([
                    'name' => $name,
                    'value' => $stored,
                ]);
                continue;
            }
            $this->moduleDb->table('settings')->where('name', $name)->update([
                'value' => $stored,
            ]);
        }

        $this->values = $values;
        $this->loadedVersion = (string)(microtime(true) * 1000);
        $this->cache->put(self::CACHE_VERSION, $this->loadedVersion);
    }

    private function ensureLoaded(): void
    {
        if ($this->values === []) {
            $this->load();
        }
    }

    private function load(): void
    {
        $this->values = [];
        foreach (self::SCHEMA as $key => $field) {
            $this->values[$key] = $field['default'];
        }

        try {
            $this->schema->ensure();
            $rows = $this->moduleDb->table('settings')->get();
        } catch (\Throwable) {
            // На свежей установке таблиц ещё нет: работаем на умолчаниях схемы.
            return;
        }

        foreach ($rows as $row) {
            $name = (string)($row->name ?? '');
            if ($name === '' || !array_key_exists($name, self::SCHEMA)) {
                continue;
            }
            $field = self::SCHEMA[$name];
            $raw = $row->value;
            $this->values[$name] = match ($field['type']) {
                'int' => (int)$raw,
                'bool' => in_array((string)$raw, ['1', 'true', 'on', 'yes'], true),
                default => (string)$raw,
            };
        }

        $this->loadedVersion = $this->version();
    }
}
