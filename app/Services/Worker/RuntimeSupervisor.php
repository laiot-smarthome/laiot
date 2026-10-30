<?php

declare(strict_types=1);

namespace LaIOT\Services\Worker;

use LaIOT\Services\Cache\CacheStore;
use LaIOT\Services\Logging\ErrorRepository;
use LaIOT\Services\Modules\ModuleManager;
use LaIOT\Services\Systemd\SystemdAgentClient;
use LaIOT\Services\WebSocket\WebSocketConfig;
use Throwable;

/**
 * Автозапуск runtime и контроль systemd независимо от открытой панели.
 * 2026-10-30 — каталог процессов, перезапуски и пропавший heartbeat — ошибки.
 */
final class RuntimeSupervisor
{
    private ?string $signature = null;

    public function __construct(
        private readonly ModuleManager $modules,
        private readonly WebSocketConfig $websocket,
        private readonly SystemdAgentClient $agent,
        private readonly CacheStore $cache,
        private readonly ErrorRepository $errors
    ) {
    }

    /** @return list<array{kind:string,id:string,tasks:list<string>}> */
    public function desired(): array
    {
        $result = [];
        foreach (CoreWorker::TASKS as $task) {
            if ($task !== 'websocket' || $this->websocket->enabled) {
                $result[] = ['kind' => 'core', 'id' => $task, 'tasks' => [$task]];
            }
        }
        foreach ($this->modules->all() as $id => $module) {
            $tasks = [];
            foreach ((array)($module['tasks'] ?? []) as $name => $task) {
                if (($task['enabled'] ?? true) === true) {
                    $tasks[] = (string)$name;
                }
            }
            if ($tasks !== []) {
                $result[] = ['kind' => 'worker', 'id' => (string)$id, 'tasks' => $tasks];
            }
            $result[] = ['kind' => 'core', 'id' => 'module.' . $id, 'tasks' => ['module.' . $id]];
        }

        return $result;
    }

    public function tick(): void
    {
        try {
            $this->synchronize();
        } catch (Throwable $error) {
            $this->report('core.supervisor', 'Ошибка контроля фоновых циклов', $error->getMessage());
        }
    }

    private function synchronize(): void
    {
        $this->modules->refreshCatalog();
        $processes = $this->desired();
        $signature = json_encode($processes, JSON_THROW_ON_ERROR);
        if ($signature !== $this->signature) {
            $cores = [];
            $workers = [];
            foreach ($processes as $process) {
                $key = $process['kind'] . '.' . $process['id'];
                if ($this->signature === null) {
                    $this->cache->forget('runtime.manual_stop.' . $key);
                }
                if ($this->cache->get('runtime.manual_stop.' . $key) === true) {
                    continue;
                }
                if ($process['kind'] === 'core') {
                    $cores[] = $process['id'];
                } else {
                    $workers[] = $process['id'];
                }
            }
            $this->agent->syncCore($cores);
            $this->agent->sync($workers);
            $this->signature = $signature;
        }
        foreach ($processes as $process) {
            try {
                $status = $process['kind'] === 'core' ? $this->agent->coreStatus($process['id']) : $this->agent->status($process['id']);
                $this->observe($process['kind'], $process['id'], $status);
            } catch (Throwable $error) {
                $this->report($process['kind'] . '.' . $process['id'], 'Не удалось проверить цикл', $error->getMessage());
            }
        }
    }

    /** @param array<string, mixed> $status */
    public function observe(string $kind, string $id, array $status): void
    {
        $key = $kind . '.' . $id;
        $previous = $this->cache->get('runtime.health.' . $key, []);
        $restarts = (int)($status['restarts'] ?? 0);
        if ($restarts > (int)($previous['restarts'] ?? 0)) {
            $this->report($key, 'Цикл перезапущен после сбоя', 'systemd NRestarts=' . $restarts);
        }
        $active = ($status['active'] ?? '') === 'active';
        $manual = $this->cache->get('runtime.manual_stop.' . $key) === true;
        if (!$active && !$manual && ($status['active'] ?? '') !== 'activating') {
            $this->report($key, 'Обязательный цикл не работает', (string)($status['active'] ?? 'unknown') . '/' . (string)($status['sub'] ?? 'unknown'));
        }
        $heartbeat = (int)$this->cache->get('worker.heartbeat.' . ($kind === 'core' ? 'core.' : '') . $id, 0);
        $started = strtotime((string)($status['started'] ?? '')) ?: time();
        $stale = $active && max($heartbeat, $started) < time() - 150;
        if ($stale) {
            $this->report($key, 'Цикл перестал обновлять heartbeat', 'Нет heartbeat более 150 секунд');
            $this->agent->request(['action' => ($kind === 'core' ? 'core.' : 'worker.') . 'restart', 'module' => $id]);
        }
        $this->cache->put('runtime.health.' . $key, $status + ['stale' => $stale, 'checked_at' => date('Y-m-d H:i:s')], 30);
    }

    private function report(string $key, string $message, string $detail): void
    {
        if ($this->cache->add('runtime.failure.' . hash('sha256', $key . $message . $detail), true, 300)) {
            $this->errors->record('error', $message . ': ' . $key, ['process' => $key, 'detail' => $detail], '');
        }
    }
}
