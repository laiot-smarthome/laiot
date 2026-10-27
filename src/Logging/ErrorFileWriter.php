<?php

declare(strict_types=1);

namespace MajorDoMo\Logging;

use Throwable;

/**
 * Отдельный файл на каждую ошибку.
 *
 * Общий журнал удобен для потока, но разбирать конкретную ошибку в нём
 * неудобно: рядом лежат тысячи строк. Поэтому каждая запись `system_errors`
 * получает свой файл с контекстом и stack trace, а общий лог остаётся
 * потоком. Файл удаляется вместе с записью журнала.
 *
 * Имя файла не принимается снаружи: только сгенерированное, без `..` и
 * абсолютных путей.
 *
 * 2026-10-27 — писатель создан вместе с файлами ошибок.
 */
final readonly class ErrorFileWriter
{
    public function __construct(private string $directory)
    {
    }

    /**
     * @param array<string, mixed> $context
     * @return string|null Имя файла для `system_errors.log_file` или null при сбое.
     */
    public function write(
        int $id,
        string $level,
        string $message,
        array $context,
        string $requestId,
        ?Throwable $exception = null
    ): ?string {
        if ($id <= 0 || $this->directory === '') {
            return null;
        }
        if (!is_dir($this->directory)
            && !mkdir($this->directory, 0775, true)
            && !is_dir($this->directory)) {
            return null;
        }

        $file = date('Ymd-His') . '-' . $id . '-' . bin2hex(random_bytes(3)) . '.log';
        $body = $this->body($id, $level, $message, $context, $requestId, $exception);

        return file_put_contents($this->path($file), $body, LOCK_EX) === false ? null : $file;
    }

    /** Удаляет файл ошибки; имя проверяется, чтобы не выйти из каталога. */
    public function delete(?string $file): bool
    {
        if ($file === null || $file === '' || basename($file) !== $file) {
            return false;
        }
        $path = $this->path($file);

        return is_file($path) && @unlink($path);
    }

    private function path(string $file): string
    {
        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $file;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function body(
        int $id,
        string $level,
        string $message,
        array $context,
        string $requestId,
        ?Throwable $exception
    ): string {
        $lines = [
            '=== MyDoMo error ' . date('Y-m-d H:i:s') . ' ===',
            'id: ' . $id,
            'level: ' . $level,
            'request_id: ' . $requestId,
            'message: ' . $message,
        ];

        if ($exception !== null) {
            $lines[] = 'error: ' . $exception->getMessage();
            $lines[] = 'class: ' . $exception::class;
            $lines[] = 'file: ' . $exception->getFile() . ':' . $exception->getLine();
        } elseif (isset($context['class'])) {
            $lines[] = 'class: ' . (string)$context['class'];
            $lines[] = 'file: ' . (string)($context['file'] ?? '') . ':' . (string)($context['line'] ?? '');
        }
        if (isset($context['path'])) {
            $lines[] = 'path: ' . (string)$context['path'];
        }

        $lines[] = 'context: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $lines[] = '';
        $lines[] = 'trace:';
        $lines[] = $exception !== null ? $exception->getTraceAsString() : '(нет)';
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }
}
