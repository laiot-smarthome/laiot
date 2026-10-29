<?php

declare(strict_types=1);

namespace LaIOT\Services\Worker;

use LaIOT\Services\Cache\CacheStore;

/**
 * Состояние фоновых задач в Redis (через CacheStore).
 *
 * Redis — рекомендуемый и фактически обязательный драйвер для production:
 * lock, heartbeat и next_run не должны зависеть от таблиц MySQL. В тестах
 * допустим array-драйвер.
 */
final readonly class BackgroundTaskState
{
    private const TASK_TTL = 86400;
    private const LOCK_TTL = 120;

    public function __construct(private CacheStore $cache)
    {
    }

    public function acquire(string $module, int $pid): bool
    {
        return $this->cache->add($this->lockKey($module), $pid, self::LOCK_TTL);
    }

    /**
     * Перехват кэш-блокировки после рестарта процесса.
     *
     * Файловая ProcessLock уже подтвердила, что worker на этой установке
     * один; оставшийся ключ — след жёсткого kill/рестарта контейнера, а не
     * второго процесса. Ждать его TTL 120 секунд не нужно.
     */
    public function takeover(string $module, int $pid): void
    {
        $this->cache->put($this->lockKey($module), $pid, self::LOCK_TTL);
    }

    public function acquireTask(string $module, string $task, int $pid): bool
    {
        return $this->cache->add($this->taskLockKey($module, $task), $pid, self::LOCK_TTL);
    }

    public function takeoverTask(string $module, string $task, int $pid): void
    {
        $this->cache->put($this->taskLockKey($module, $task), $pid, self::LOCK_TTL);
    }

    public function refresh(string $module, int $pid): void
    {
        $current = $this->cache->get($this->lockKey($module));
        if ((int)$current === $pid) {
            $this->cache->put($this->lockKey($module), $pid, self::LOCK_TTL);
        }
    }

    public function refreshTask(string $module, string $task, int $pid): void
    {
        $key = $this->taskLockKey($module, $task);
        if ((int)$this->cache->get($key) === $pid) {
            $this->cache->put($key, $pid, self::LOCK_TTL);
        }
    }

    public function release(string $module, int $pid): void
    {
        if ((int)$this->cache->get($this->lockKey($module)) === $pid) {
            $this->cache->forget($this->lockKey($module));
        }
    }

    public function releaseTask(string $module, string $task, int $pid): void
    {
        $key = $this->taskLockKey($module, $task);
        if ((int)$this->cache->get($key) === $pid) {
            $this->cache->forget($key);
        }
    }

    public function start(string $module, string $task, int $pid, string $nextRunAt): void
    {
        $now = date('Y-m-d H:i:s');
        $this->cache->put($this->taskKey($module, $task), [
            'module' => $module,
            'task' => $task,
            'pid' => $pid,
            'status' => 'running',
            'started_at' => $now,
            'finished_at' => null,
            'next_run_at' => $nextRunAt,
            'error' => null,
            'updated_at' => $now,
        ], self::TASK_TTL);
    }

    public function finish(string $module, string $task, string $status, ?string $error, string $nextRunAt): void
    {
        $state = $this->get($module, $task) ?? [];
        $state['module'] = $module;
        $state['task'] = $task;
        $state['status'] = $status;
        $state['finished_at'] = date('Y-m-d H:i:s');
        $state['next_run_at'] = $nextRunAt;
        $state['error'] = $error;
        $state['updated_at'] = date('Y-m-d H:i:s');
        $this->cache->put($this->taskKey($module, $task), $state, self::TASK_TTL);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $module, string $task): ?array
    {
        $state = $this->cache->get($this->taskKey($module, $task));

        return is_array($state) ? $state : null;
    }

    /**
     * @param array<string, array<string, mixed>> $tasks
     * @return array<string, array<string, mixed>>
     */
    public function forModule(string $module, array $tasks): array
    {
        $state = [];
        foreach (array_keys($tasks) as $task) {
            $value = $this->get($module, $task);
            if ($value !== null) {
                $state[$task] = $value;
            }
        }

        return $state;
    }

    private function taskKey(string $module, string $task): string
    {
        return 'worker.task.' . $module . '.' . $task;
    }

    private function lockKey(string $module): string
    {
        return 'worker.lock.' . $module;
    }

    private function taskLockKey(string $module, string $task): string
    {
        return 'worker.lock.' . $module . '.' . $task;
    }
}
