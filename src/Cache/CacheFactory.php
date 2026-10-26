<?php

declare(strict_types=1);

namespace MajorDoMo\Cache;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Cache\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Redis\RedisManager;
use MajorDoMo\Database\DatabaseManager;
use RuntimeException;

/**
 * Сборка CacheStore по конфигурации.
 *
 * Redis-драйвер появится отдельным этапом; сейчас поддерживаются array,
 * file и database, чтобы ядро и тесты не зависели от инфраструктуры.
 */
final class CacheFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public static function create(array $config, DatabaseManager $database): CacheStore
    {
        $driver = (string)($config['driver'] ?? 'file');
        $prefix = (string)($config['prefix'] ?? 'mydomo.');

        return match ($driver) {
            'array' => new CacheStore(new Repository(new ArrayStore()), $prefix),
            'file' => new CacheStore(new Repository(self::fileStore($config)), $prefix),
            'database' => new CacheStore(
                new Repository(new DatabaseStore(
                    $database->connection(),
                    (string)($config['table'] ?? 'cache'),
                    ''
                )),
                $prefix
            ),
            'redis' => self::redisStore($config, $prefix),
            default => throw new RuntimeException('Неподдерживаемый драйвер кэша: ' . $driver),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function redisStore(array $config, string $prefix): CacheStore
    {
        $redisConfig = (array)($config['redis'] ?? []);
        /** @phpstan-ignore argument.type */
        $manager = new RedisManager(new Container(), 'predis', [
            'default' => array_filter([
                'host' => (string)($redisConfig['host'] ?? '127.0.0.1'),
                'port' => (int)($redisConfig['port'] ?? 6379),
                'database' => (int)($redisConfig['database'] ?? 0),
                'password' => (string)($redisConfig['password'] ?? ''),
            ], static fn (mixed $value): bool => $value !== ''),
        ]);

        $clear = static function () use ($manager, $prefix): bool {
            $connection = $manager->connection();
            $cursor = '0';
            do {
                /** @var mixed $result */
                $result = $connection->scan($cursor, $prefix . '*');
                if (!is_array($result) || count($result) < 2) {
                    break;
                }
                [$cursor, $keys] = $result;
                if (is_array($keys) && $keys !== []) {
                    $connection->del($keys);
                }
            } while ((string)$cursor !== '0');

            return true;
        };

        return new CacheStore(new Repository(new RedisStore($manager, '', 'default')), $prefix, $clear);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function fileStore(array $config): FileStore
    {
        $directory = (string)($config['path'] ?? sys_get_temp_dir() . '/mydomo-cache');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Не удалось создать каталог кэша: ' . $directory);
        }

        return new FileStore(new Filesystem(), $directory);
    }
}
