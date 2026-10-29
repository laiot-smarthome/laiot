<?php

declare(strict_types=1);

namespace MajorDoMo\Http;

use SplFileObject;
use Throwable;

/**
 * Автономная диагностика: шаблон не зависит от Composer, Blade, DI и БД.
 *
 * Аргументы вызовов и содержимое запроса не собираются даже в debug.
 * Исходники читаются только внутри проекта и только для PHP-файлов.
 *
 * Изменения:
 * 2026-10-29 — текстовый отчёт для копирования ошибки в ИИ.
 * 2026-10-29 — страница исключения с исходниками, кадрами стека и причинами.
 *
 * @phpstan-type Frame array{file:string,line:int,call:string,vendor:bool,source:array<int,string>}
 * @phpstan-type ExceptionDetail array{class:string,message:string,frames:list<Frame>,truncated:bool}
 */
final class ExceptionPage
{
    public static function render(
        Throwable $error,
        bool $debug,
        string $requestId,
        string $path,
        string $method,
        ?string $root = null
    ): string {
        $root ??= dirname(__DIR__, 2);
        $exceptions = $debug ? self::exceptions($error, $root) : [];
        $report = $debug ? self::report($exceptions, $requestId, $path, $method) : '';
        $asset = $root . '/public/assets/admin/mydomo.css';
        $assetVersion = is_file($asset) ? '?v=' . filemtime($asset) : '';
        $script = $root . '/public/assets/admin/mydomo-exceptions.js';
        $scriptVersion = is_file($script) ? '?v=' . filemtime($script) : '';
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Незавершённый HTML не должен попасть в ответ, если сломан и шаблон.
        ob_start();
        try {
            require __DIR__ . '/../../resources/views/errors/exception.php';

            return (string)ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /** @param list<ExceptionDetail> $exceptions */
    private static function report(array $exceptions, string $requestId, string $path, string $method): string
    {
        $lines = ['LaIoT — ошибка приложения', 'PHP: ' . PHP_VERSION, 'Запрос: ' . $method . ' ' . $path, 'Request ID: ' . $requestId];
        foreach ($exceptions as $index => $exception) {
            $lines[] = '';
            $lines[] = ($index > 0 ? 'Причина: ' : '') . $exception['class'] . ': ' . $exception['message'];
            $location = $exception['frames'][0];
            $lines[] = $location['file'] . ':' . $location['line'];
            if ($location['source'] !== []) {
                $lines[] = 'Исходник:';
                foreach ($location['source'] as $number => $code) {
                    $lines[] = ($number === $location['line'] ? '> ' : '  ') . $number . ' | ' . $code;
                }
            }
            $lines[] = 'Трассировка:';
            foreach ($exception['frames'] as $number => $frame) {
                $lines[] = '#' . $number . ' ' . $frame['file'] . ':' . $frame['line'] . ' ' . $frame['call'];
            }
            if ($exception['truncated']) {
                $lines[] = '[Трассировка сокращена до 64 вызовов]';
            }
        }

        return implode("\n", $lines);
    }

    /** @return list<ExceptionDetail> */
    private static function exceptions(Throwable $error, string $root): array
    {
        $exceptions = [];
        do {
            $frames = [self::frame($error->getFile(), $error->getLine(), 'Место ошибки', $root)];
            foreach (array_slice($error->getTrace(), 0, 64) as $frame) {
                $frames[] = self::frame(
                    $frame['file'] ?? '',
                    $frame['line'] ?? 0,
                    ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'] . '()',
                    $root
                );
            }
            $exceptions[] = [
                'class' => $error::class,
                'message' => $error->getMessage(),
                'frames' => $frames,
                'truncated' => count($error->getTrace()) > 64,
            ];
            $error = $error->getPrevious();
        } while ($error !== null && count($exceptions) < 8);

        return $exceptions;
    }

    /** @return Frame */
    private static function frame(string $file, int $line, string $call, string $root): array
    {
        $normalized = str_replace('\\', '/', $file);
        $prefix = rtrim(str_replace('\\', '/', $root), '/') . '/';

        return [
            'file' => str_starts_with($normalized, $prefix) ? substr($normalized, strlen($prefix)) : $normalized,
            'line' => $line,
            'call' => $call,
            'vendor' => str_contains('/' . $normalized, '/vendor/'),
            'source' => self::source($file, $line, $root),
        ];
    }

    /** @return array<int, string> */
    private static function source(string $file, int $line, string $root): array
    {
        if ($line < 1 || $line > 100000 || !str_ends_with($file, '.php')) {
            return [];
        }
        $resolved = realpath($file);
        $project = realpath($root);
        if ($resolved === false || $project === false
            || !str_starts_with($resolved, $project . DIRECTORY_SEPARATOR)
            || !is_file($resolved) || !is_readable($resolved)
            || filesize($resolved) > 2 * 1024 * 1024) {
            return [];
        }

        try {
            $source = new SplFileObject($resolved);
            $first = max(1, $line - 5);
            $source->seek($first - 1);
            $lines = [];
            for ($number = $first; $number <= $line + 5 && !$source->eof(); ++$number) {
                $lines[$number] = rtrim(substr($source->fgets(), 0, 1000), "\r\n");
            }

            return $lines;
        } catch (Throwable $failure) {
            // Файл мог исчезнуть между проверкой и чтением; сохраняем сам стек.
            error_log('MyDoMo: cannot read exception source: ' . $failure->getMessage());

            return [];
        }
    }
}
