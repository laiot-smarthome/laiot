<?php

declare(strict_types=1);

namespace LaIOT\Services\Systemd;

use LaIOT\Services\Worker\CoreWorker;
use RuntimeException;

/**
 * Запускает защищённую службу обновления под пользователем сайта, без произвольных root-команд.
 * 2026-10-29 — фиксированные действия запуска и остановки фонового runtime.
 */
final readonly class UpdateAgent
{
    public function __construct(private ProcessRunner $runner, private string $user, private string $systemctl = 'systemctl')
    {
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handle(array $payload): array
    {
        $action = (string)($payload['action'] ?? '');
        $owner = $this->runner->run([$this->systemctl, 'show', 'laiot-update.service', '--property=User', '--value']);
        if (!$owner->successful() || trim($owner->stdout) !== $this->user || $this->user === 'root') {
            throw new RuntimeException('Нет настроенной laiot-update.service под пользователем сайта. Выполните deploy/setup-ubuntu.sh.');
        }
        if (in_array($action, ['update.start', 'update.agent.restart'], true)) {
            $restart = $action === 'update.agent.restart';
            $result = $this->runner->run([$this->systemctl, $restart ? 'restart' : 'start', '--no-block', $restart ? 'laiot-agent.service' : 'laiot-update.service']);
            if (!$result->successful()) {
                throw new RuntimeException('systemd не запустил laiot-update.service.');
            }
            return ['started' => true];
        }
        if ($action === 'update.services') {
            $states = ($payload['include_starting'] ?? false) === true ? 'active,activating,reloading,deactivating' : 'active';
            $result = $this->runner->run([$this->systemctl, 'list-units', '--type=service', '--state=' . $states, '--no-legend', '--plain', 'laiot-core@*', 'laiot-worker@*']);
            if (!$result->successful()) {
                throw new RuntimeException('Не удалось получить список фоновых служб.');
            }
            $units = [];
            foreach (preg_split('/\R/', $result->stdout) ?: [] as $line) {
                $unit = strtok(trim($line), ' ');
                if (is_string($unit) && $this->allowed($unit)) {
                    $units[] = $unit;
                }
            }
            $account = posix_getpwnam($this->user);
            if ($account === false) {
                throw new RuntimeException('Пользователь службы обновления не найден.');
            }
            return ['units' => $units, 'uid' => $account['uid']];
        }
        if (!in_array($action, ['update.services.stop', 'update.services.start'], true) || !is_array($payload['units'] ?? null)) {
            throw new RuntimeException('Недопустимое действие обновителя.');
        }
        $units = array_values($payload['units']);
        foreach ($units as $unit) {
            if (!is_string($unit) || !$this->allowed($unit)) {
                throw new RuntimeException('Обновитель не может управлять указанной службой.');
            }
        }
        if ($units !== []) {
            $result = $this->runner->run(array_merge([$this->systemctl, str_ends_with($action, '.stop') ? 'stop' : 'start'], $units), 180);
            if (!$result->successful()) {
                throw new RuntimeException('Не удалось переключить фоновые службы.');
            }
        }
        return ['units' => $units];
    }

    private function allowed(string $unit): bool
    {
        if (preg_match('/^laiot-worker@[a-z][a-z0-9_]{1,63}\.service$/D', $unit) === 1) {
            return true;
        }
        if (preg_match('/^laiot-core@([a-z][a-z0-9_.-]{1,63})\.service$/D', $unit, $match) === 1) {
            return in_array($match[1], CoreWorker::TASKS, true) || preg_match('/^module\.[a-z][a-z0-9_]{1,63}$/D', $match[1]) === 1
                || preg_match('/^events\.[0-9]+$/D', $match[1]) === 1;
        }
        return false;
    }
}
