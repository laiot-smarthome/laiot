<?php

declare(strict_types=1);

namespace LaIOT\Services\Notifications;

use LaIOT\Services\Methods\MethodContext;
use LaIOT\Services\Methods\MethodRunner;
use LaIOT\Services\Scripts\ScriptExecutor;
use LaIOT\Services\Scripts\ScriptRunner;
use LaIOT\Services\Webhooks\WebhookService;

/**
 * Источник say по ближайшему вызывающему модулю или исполнителю кода.
 *
 * 2026-10-29 — источник определяется без ручного аргумента и чтения параметров стека.
 */
final readonly class SaySource
{
    public function __construct(private string $root)
    {
    }

    public function current(): string
    {
        $modulesPath = rtrim(str_replace('\\', '/', $this->root), '/') . '/modules/';
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $file = str_replace('\\', '/', $frame['file'] ?? '');
            if (str_starts_with($file, $modulesPath)
                && preg_match('/^(?:core\/)?([a-z][a-z0-9_]{1,63})\//D', substr($file, strlen($modulesPath)), $match) === 1) {
                // SDK проверяет, что имя каталога совпадает с id манифеста.
                return $match[1];
            }

            $source = match ($frame['class'] ?? '') {
                MethodContext::class, MethodRunner::class => 'methods',
                ScriptExecutor::class, ScriptRunner::class => 'scripts',
                WebhookService::class => 'webhooks',
                default => null,
            };
            if ($source !== null) {
                return $source;
            }
        }

        return 'system';
    }
}
