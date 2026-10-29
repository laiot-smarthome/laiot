<?php

declare(strict_types=1);

namespace LaIOT\Services\Updates;

use LaIOT\Services\Systemd\SystemdAgentClient;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Обновитель собирается без boot приложения: незавершённая миграция не должна мешать восстановлению.
 * 2026-10-29 — одинаковые зависимости для веб-панели и отдельной CLI-службы.
 */
final class UpdateFactory
{
    /** @param array<string, mixed> $config */
    public static function make(string $root, array $config): UpdateService
    {
        $layout = new ReleaseLayout($root);
        return new UpdateService(
            $layout,
            new UpdateState($layout->storage . '/updates'),
            new ReleaseCatalog(HttpClient::create(), (string)($config['updates']['repository'] ?? 'laiot-smarthome/laiot')),
            new UpdateDatabase((array)($config['database'] ?? []), (string)($config['encryption']['key'] ?? ''), $layout->storage . '/backups'),
            new UpdateHost(new SystemdAgentClient('/run/laiot/agent.sock', 200)),
            $config
        );
    }
}
