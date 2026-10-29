<?php

declare(strict_types=1);

namespace LaIOT\Services\Updates;

use RuntimeException;

/**
 * Законченные записи журнала заменяются атомарно: оборванная запись не теряет прежнее состояние.
 * 2026-10-29 — файловое состояние обновления вне базы, которая может восстанавливаться.
 */
final class UpdateFiles
{
    public static function directory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('Не удалось создать каталог обновления.');
        }
    }

    /** @return array<string, mixed> */
    public static function read(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $value = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new RuntimeException('Повреждён журнал обновления: ' . basename($path));
        }
        return $value;
    }

    /** @param array<string, mixed> $value */
    public static function json(string $path, array $value): void
    {
        self::write($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public static function write(string $path, string $contents): void
    {
        self::directory(dirname($path));
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $stream = fopen($temporary, 'xb');
        if ($stream === false) {
            throw new RuntimeException('Не удалось записать файл обновления.');
        }
        try {
            chmod($temporary, 0600);
            $offset = 0;
            while ($offset < strlen($contents)) {
                $written = fwrite($stream, substr($contents, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Не удалось полностью записать файл обновления.');
                }
                $offset += $written;
            }
            if (!fflush($stream) || !fsync($stream)) {
                throw new RuntimeException('Не удалось синхронизировать файл обновления.');
            }
        } finally {
            fclose($stream);
        }
        if (!rename($temporary, $path)) {
            throw new RuntimeException('Не удалось заменить файл обновления.');
        }
        self::syncDirectory(dirname($path));
    }

    public static function link(string $target, string $path): void
    {
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.link';
        if (!symlink($target, $temporary) || !rename($temporary, $path)) {
            throw new RuntimeException('Не удалось переключить ссылку ' . basename($path));
        }
        self::syncDirectory(dirname($path));
    }

    public static function syncDirectory(string $path): void
    {
        // Рабочая платформа — Linux: fsync каталога закрепляет rename и при внезапном отключении питания.
        if (PHP_OS_FAMILY === 'Linux') {
            $stream = fopen($path, 'rb');
            if ($stream === false || !fsync($stream)) {
                throw new RuntimeException('Не удалось синхронизировать каталог обновления.');
            }
            fclose($stream);
        }
    }

    public static function syncFile(string $path): void
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $stream = fopen($path, 'rb');
            if ($stream === false || !fsync($stream)) {
                throw new RuntimeException('Не удалось синхронизировать файл релиза.');
            }
            fclose($stream);
        }
    }
}
