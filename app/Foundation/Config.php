<?php

declare(strict_types=1);

namespace LaIOT\Foundation;

/**
 * Чтение `.env` без legacy Config из alpha.
 *
 * Файл `.env` — простые KEY=VALUE, комментарии и кавычки; подстановка
 * переменных не поддерживается. Значения читаются в типизированном виде,
 * чтобы bootstrap не зависел от alpha `LaIOT\Core\Config`.
 *
 * 2026-10-27 — замена alpha Config после удаления lib/.
 * 2026-10-27 — общий разбор файла для чтения и редактора окружения.
 */
final class Config
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function boot(string $root): void
    {
        self::$values = [];
        $file = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }
        self::$values = self::parse(implode("\n", $lines));
    }

    /** @return array<string, string> */
    public static function parse(string $contents): array
    {
        $values = [];
        foreach (explode("\n", $contents) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }
            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }
            $key = trim(substr($line, 0, $separator));
            if ($key === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $key) !== 1) {
                continue;
            }
            $value = trim(substr($line, $separator + 1));
            if (strlen($value) >= 2
                && (($value[0] === '"' && $value[strlen($value) - 1] === '"')
                    || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))) {
                $value = substr($value, 1, -1);
            }
            $values[$key] = $value;
        }

        return $values;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value === false) {
            $value = self::$values[$key] ?? null;
        }
        if (!is_string($value)) {
            return $default;
        }

        return $value;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::string($key);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return $default;
        }

        return (int)$value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::string($key);
        if ($value === '') {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::$values;
    }
}
