<?php

declare(strict_types=1);

namespace MajorDoMo\Install;

/**
 * Параметры установки, введённые администратором.
 *
 * Объект не проверяет себя сам: правила живут в InstallService, чтобы
 * страница и CLI показывали одинаковые ошибки. Здесь только приведение
 * HTTP/CLI-значений к типам и сборка конфигурации для нового Application.
 *
 * 2026-10-28 — параметры отделены от формы и от применения.
 */
final readonly class InstallRequest
{
    public function __construct(
        public string $dbHost = '127.0.0.1',
        public int $dbPort = 3306,
        public string $dbName = '',
        public string $dbUser = '',
        public string $dbPassword = '',
        public bool $dbCreate = false,
        public string $cacheDriver = 'redis',
        public string $redisHost = '127.0.0.1',
        public int $redisPort = 6379,
        public int $redisDatabase = 0,
        public string $redisPassword = '',
        public string $adminLogin = '',
        public string $adminName = '',
        public string $adminPassword = '',
        public string $timezone = 'Europe/Moscow'
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function fromArray(array $input): self
    {
        $driver = self::text($input, 'cache_driver', 'redis');

        return new self(
            dbHost: self::text($input, 'db_host', '127.0.0.1'),
            dbPort: self::number($input, 'db_port', 3306),
            dbName: self::text($input, 'db_name'),
            dbUser: self::text($input, 'db_user'),
            dbPassword: self::text($input, 'db_password'),
            dbCreate: self::flag($input, 'db_create'),
            cacheDriver: in_array($driver, ['redis', 'file'], true) ? $driver : 'redis',
            redisHost: self::text($input, 'redis_host', '127.0.0.1'),
            redisPort: self::number($input, 'redis_port', 6379),
            redisDatabase: self::number($input, 'redis_database', 0),
            redisPassword: self::text($input, 'redis_password'),
            adminLogin: self::text($input, 'admin_login'),
            adminName: self::text($input, 'admin_name'),
            adminPassword: self::text($input, 'admin_password'),
            timezone: self::text($input, 'timezone', 'Europe/Moscow')
        );
    }

    /**
     * Значения формы по умолчанию: базу создаём, кэш — Redis.
     *
     * @return array<string, mixed>
     */
    public static function formDefaults(): array
    {
        return [
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_name' => 'mydomo',
            'db_user' => 'mydomo',
            'db_password' => '',
            'db_create' => true,
            'cache_driver' => 'redis',
            'redis_host' => '127.0.0.1',
            'redis_port' => 6379,
            'redis_database' => 0,
            'redis_password' => '',
            'admin_login' => 'sysadmin',
            'admin_name' => 'Администратор',
            'admin_password' => '',
            'timezone' => 'Europe/Moscow',
        ];
    }

    /**
     * Поля для общего валидатора: имена совпадают с полями формы.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'db_host' => $this->dbHost,
            'db_port' => $this->dbPort,
            'db_name' => $this->dbName,
            'db_user' => $this->dbUser,
            'db_password' => $this->dbPassword,
            'db_create' => $this->dbCreate,
            'cache_driver' => $this->cacheDriver,
            'redis_host' => $this->redisHost,
            'redis_port' => $this->redisPort,
            'redis_database' => $this->redisDatabase,
            'redis_password' => $this->redisPassword,
            'admin_login' => $this->adminLogin,
            'admin_name' => $this->adminName,
            'admin_password' => $this->adminPassword,
            'timezone' => $this->timezone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function databaseConfig(): array
    {
        return [
            'driver' => 'mysql',
            'host' => $this->dbHost,
            'port' => $this->dbPort,
            'database' => $this->dbName,
            'username' => $this->dbUser,
            'password' => $this->dbPassword,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cacheConfig(string $root): array
    {
        if ($this->cacheDriver === 'redis') {
            return [
                'driver' => 'redis',
                'prefix' => 'mydomo.',
                'redis' => [
                    'host' => $this->redisHost,
                    'port' => $this->redisPort,
                    'database' => $this->redisDatabase,
                    'password' => $this->redisPassword,
                ],
            ];
        }

        return [
            'driver' => 'file',
            'path' => rtrim($root, '/\\') . '/var/cache/data',
            'prefix' => 'mydomo.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function text(array $input, string $key, string $default = ''): string
    {
        $value = $input[$key] ?? null;

        return is_string($value) ? trim($value) : $default;
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function number(array $input, string $key, int $default): int
    {
        $value = $input[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        $filtered = is_string($value) ? filter_var(trim($value), FILTER_VALIDATE_INT) : false;

        return $filtered === false ? $default : (int)$filtered;
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function flag(array $input, string $key): bool
    {
        $value = $input[$key] ?? false;

        return in_array($value, [true, 1, '1', 'on', 'yes', 'true'], true);
    }
}
