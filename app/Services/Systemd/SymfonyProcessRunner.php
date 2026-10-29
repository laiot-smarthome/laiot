<?php

declare(strict_types=1);

namespace LaIOT\Services\Systemd;

use Symfony\Component\Process\Process;

/**
 * Единственное место, где агент запускает системные команды.
 *
 * Команда передаётся массивом, без shell-интерполяции.
 */
final readonly class SymfonyProcessRunner implements ProcessRunner
{
    /**
     * @param list<string> $command
     */
    public function run(array $command, int $timeout = 20): ProcessResult
    {
        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->run();

        return new ProcessResult(
            $process->getExitCode() ?? 1,
            $process->getOutput(),
            $process->getErrorOutput()
        );
    }
}
