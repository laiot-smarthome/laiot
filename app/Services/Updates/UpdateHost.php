<?php

declare(strict_types=1);

namespace LaIOT\Services\Updates;

use LaIOT\Services\Systemd\SystemdAgentClient;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Работа с Linux-службами остаётся за агентом; код релиза исполняется пользователем сайта.
 * 2026-10-29 — проверка CLI, миграции и запуск нового ядра до открытия записи.
 */
class UpdateHost
{
    public function __construct(private readonly SystemdAgentClient $agent)
    {
    }

    /** @return list<string> */
    public function services(bool $includeStarting = false): array
    {
        $data = $this->agent->request(['action' => 'update.services', 'include_starting' => $includeStarting]);
        if (!isset($data['uid']) || !function_exists('posix_geteuid') || (int)$data['uid'] !== posix_geteuid()) {
            throw new RuntimeException('Пользователь PHP отличается от пользователя службы обновления.');
        }
        return array_values(array_map('strval', (array)($data['units'] ?? [])));
    }

    /** @param list<string> $units */
    public function control(string $action, array $units): void
    {
        if ($units === []) {
            return;
        }
        $this->agent->request(['action' => 'update.services.' . $action, 'units' => $units]);
    }

    public function launch(): void
    {
        $this->agent->request(['action' => 'update.start']);
    }

    public function restartAgent(): void
    {
        $this->agent->request(['action' => 'update.agent.restart']);
    }

    public function probe(string $root, bool $migrate = false, bool $platformOnly = false): void
    {
        $command = [PHP_BINARY, $root . '/bin/laiot', 'update:probe'];
        if ($migrate) {
            $command[] = '--migrate';
        }
        if ($platformOnly) {
            $command[] = '--platform-only';
        }
        $process = new Process($command, $root, null, null, 600);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException('Проверка релиза не пройдена: ' . mb_substr(trim($process->getErrorOutput() . $process->getOutput()), 0, 1500));
        }
    }
}
