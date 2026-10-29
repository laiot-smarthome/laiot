<?php

declare(strict_types=1);

namespace LaIOT\Services\Systemd;

use RuntimeException;

/**
 * Клиент привилегированного laiot-agent.
 *
 * PHP отправляет только action + module; конфиги и команды systemd остаются
 * на стороне агента.
 */
final readonly class SystemdAgentClient
{
    public function __construct(
        private string $socketPath = '/run/laiot/agent.sock',
        private float $timeout = 10.0
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function start(string $module): array
    {
        return $this->request(['action' => 'worker.start', 'module' => $module]);
    }

    /**
     * @return array<string, mixed>
     */
    public function stop(string $module): array
    {
        return $this->request(['action' => 'worker.stop', 'module' => $module]);
    }

    /**
     * @return array<string, mixed>
     */
    public function restart(string $module): array
    {
        return $this->request(['action' => 'worker.restart', 'module' => $module]);
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $module): array
    {
        return $this->request(['action' => 'worker.status', 'module' => $module]);
    }

    /**
     * @param list<string> $modules
     * @return array<string, mixed>
     */
    public function sync(array $modules): array
    {
        return $this->request(['action' => 'workers.sync', 'modules' => $modules]);
    }

    /**
     * @return array<string, mixed>
     */
    public function coreStart(string $task): array
    {
        return $this->request(['action' => 'core.start', 'module' => $task]);
    }

    /**
     * @return array<string, mixed>
     */
    public function coreStop(string $task): array
    {
        return $this->request(['action' => 'core.stop', 'module' => $task]);
    }

    /**
     * @return array<string, mixed>
     */
    public function coreRestart(string $task): array
    {
        return $this->request(['action' => 'core.restart', 'module' => $task]);
    }

    /**
     * @return array<string, mixed>
     */
    public function coreStatus(string $task): array
    {
        return $this->request(['action' => 'core.status', 'module' => $task]);
    }

    /**
     * @param list<string> $tasks
     * @return array<string, mixed>
     */
    public function syncCore(array $tasks): array
    {
        return $this->request(['action' => 'cores.sync', 'tasks' => $tasks]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function request(array $payload): array
    {
        $socket = @stream_socket_client(
            'unix://' . $this->socketPath,
            $errorCode,
            $errorMessage,
            $this->timeout
        );
        if ($socket === false) {
            throw new RuntimeException('laiot-agent недоступен: ' . $errorMessage);
        }
        stream_set_timeout($socket, (int)$this->timeout);
        fwrite($socket, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        $line = fgets($socket);
        fclose($socket);
        if ($line === false) {
            throw new RuntimeException('laiot-agent не ответил.');
        }
        $response = json_decode(trim($line), true);
        if (!is_array($response)) {
            throw new RuntimeException('Некорректный ответ laiot-agent.');
        }
        if (($response['ok'] ?? false) !== true) {
            throw new RuntimeException((string)($response['error'] ?? 'Ошибка laiot-agent.'));
        }

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }
}
