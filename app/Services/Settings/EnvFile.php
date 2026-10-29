<?php

declare(strict_types=1);

namespace LaIOT\Services\Settings;

use LaIOT\Foundation\Config;
use RuntimeException;

/**
 * Атомарная запись окружения с сохранением посторонних ключей и комментариев.
 *
 * 2026-10-27 — блокировка и ревизия защищают от сохранения устаревшей формы.
 */
final readonly class EnvFile
{
    public function __construct(private string $path)
    {
    }

    public function contents(): string
    {
        if (is_link($this->path)) {
            throw new RuntimeException('Редактирование .env через символическую ссылку запрещено.');
        }
        if (!file_exists($this->path)) {
            return '';
        }
        if (!is_file($this->path) || !is_readable($this->path)) {
            throw new RuntimeException('Файл .env недоступен для чтения.');
        }
        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new RuntimeException('Не удалось прочитать .env.');
        }

        return $contents;
    }

    /** @param array<string, string> $changes */
    public function save(array $changes, string $revision): void
    {
        foreach ($changes as $key => $value) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $key) !== 1) {
                throw new RuntimeException('Некорректное имя параметра .env.');
            }
        }
        $directory = dirname($this->path);
        if (!is_writable($directory) || (file_exists($this->path) && !is_writable($this->path))) {
            throw new RuntimeException('Для сохранения нужны права записи на .env и каталог установки.');
        }
        $lockPath = $this->path . '.lock';
        if (is_link($lockPath)) {
            throw new RuntimeException('Файл блокировки .env недоступен.');
        }
        $mask = umask(0077);
        try {
            $lock = fopen($lockPath, 'c');
        } finally {
            umask($mask);
        }
        if ($lock === false) {
            throw new RuntimeException('Не удалось заблокировать .env.');
        }
        $temporary = null;
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Не удалось заблокировать .env.');
            }
            $contents = $this->contents();
            if (!hash_equals(hash('sha256', $contents), $revision)) {
                throw new RuntimeException('Настройки уже изменены в другом окне или на сервере. Обновите страницу и повторите изменения.');
            }
            $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
            $lines = $contents === '' ? [] : explode($newline, rtrim($contents, "\r\n"));
            $seen = [];
            foreach ($lines as &$line) {
                if (preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $match) !== 1 || !array_key_exists($match[1], $changes)) {
                    continue;
                }
                $key = $match[1];
                // Обновляем все вхождения: последнее старое значение не должно
                // неожиданно перекрыть новое при следующем чтении файла.
                $line = $key . '=' . $this->encode($changes[$key]);
                $seen[$key] = true;
            }
            unset($line);
            foreach ($changes as $key => $value) {
                if (!isset($seen[$key])) {
                    $lines[] = $key . '=' . $this->encode($value);
                }
            }
            $updated = implode($newline, $lines) . $newline;
            $parsed = Config::parse($updated);
            foreach ($changes as $key => $value) {
                if (($parsed[$key] ?? null) !== $value) {
                    throw new RuntimeException('Значение нельзя безопасно записать в .env.');
                }
            }
            $temporary = tempnam($directory, '.env-write-');
            if ($temporary === false) {
                throw new RuntimeException('Не удалось подготовить запись .env.');
            }
            $mode = file_exists($this->path) ? fileperms($this->path) : 0600;
            if ($mode === false || !chmod($temporary, $mode & 0777)
                || file_put_contents($temporary, $updated) !== strlen($updated)) {
                throw new RuntimeException('Не удалось записать .env. Исходный файл сохранён.');
            }
            if (!rename($temporary, $this->path)) {
                throw new RuntimeException('Не удалось заменить .env. Исходный файл сохранён.');
            }
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function encode(string $value): string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new RuntimeException('Значение .env не должно содержать переносы строк или управляющие символы.');
        }

        // Парсер не разворачивает escape-последовательности: кавычки внутри
        // строки остаются буквальными, снимается только внешняя пара.
        return '"' . $value . '"';
    }
}
