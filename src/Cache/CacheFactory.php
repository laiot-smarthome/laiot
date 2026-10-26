<?php

declare(strict_types=1);

namespace MajorDoMo\Cache;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\Repository;
use Illuminate\Filesystem\Filesystem;
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
            default => throw new RuntimeException('Неподдерживаемый драйвер кэша: ' . $driver),
        };
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
