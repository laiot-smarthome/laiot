<?php

declare(strict_types=1);

namespace LaIOT\Console;

use LaIOT\Console\Commands\ReleaseBuildCommand;
use LaIOT\Console\Commands\UpdateProbeCommand;
use LaIOT\Console\Commands\UpdateRunCommand;
use LaIOT\Console\Commands\UpdateStatusCommand;
use LaIOT\Services\Updates\UpdateFactory;
use Symfony\Component\Console\Command\Command;

/**
 * Общая регистрация независимых команд для обычной консоли и раннего аварийного входа.
 * 2026-10-29 — восстановление не требует загрузки модулей или доступной схемы БД.
 */
final class UpdateConsoleFactory
{
    /** @param array<string, mixed> $config
     * @return list<Command>
     */
    public static function commands(string $root, array $config): array
    {
        $updates = UpdateFactory::make($root, $config);
        return [new UpdateRunCommand($updates), new UpdateProbeCommand($root, $config), new UpdateStatusCommand($updates), new ReleaseBuildCommand($root)];
    }
}
