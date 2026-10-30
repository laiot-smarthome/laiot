<?php

declare(strict_types=1);

namespace LaIOT\Services\Xray;

use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\Rule;
use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Cache\CacheStore;
use LaIOT\Services\Events\SystemEvents;
use LaIOT\Services\Runtime\TimeoutRepository;
use LaIOT\Services\Scripts\ScriptTriggers;
use LaIOT\Services\Systemd\SystemdAgentClient;
use LaIOT\Services\Worker\BackgroundTaskState;
use LaIOT\Services\Worker\RuntimeSupervisor;
use RuntimeException;

/**
 * Снимки автоматизации и управление только известными процессами установки.
 * 2026-10-30 — таймеры, подписки, heartbeat и ограниченные команды Unix-агенту.
 */
final readonly class XrayService
{
    public function __construct(
        private DatabaseManager $database,
        private BackgroundTaskState $states,
        private CacheStore $cache,
        private TimeoutRepository $timeouts,
        private SystemEvents $events,
        private Factory $validation,
        private SystemdAgentClient $agent,
        private RuntimeSupervisor $supervisor
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function timers(string $search): array
    {
        return $this->database->connection()->table('runtime_timeouts')
            ->whereNull('deleted_at')->whereNull('executed_at')
            ->when($search !== '', static fn ($query) => $query->where('title', 'like', '%' . $search . '%'))
            ->orderBy('run_at')->limit(100)->get()->map(static fn (object $row): array => (array)$row + [
                'cancelable' => $row->claimed_at === null || (strtotime((string)$row->claimed_at) ?: 0) <= time() - 300,
            ])->all();
    }

    /** @return array{timers:int,due:int,cycles:int,running:int} */
    public function summary(): array
    {
        $timers = $this->database->connection()->table('runtime_timeouts')->whereNull('deleted_at')->whereNull('executed_at');
        $cycles = [];
        foreach ($this->cycles() as $cycle) {
            $cycles[$cycle['kind'] . ':' . $cycle['id']] = $cycle['pid'] > 0;
        }

        return ['timers' => $timers->count(), 'due' => $timers->where('run_at', '<=', date('Y-m-d H:i:s'))->count(),
            'cycles' => count($cycles), 'running' => count(array_filter($cycles))];
    }

    public function cancelTimer(int $id): void
    {
        $input = $this->validation->make(['id' => $id], ['id' => ['required', 'integer', 'min:1']])->validate();
        if (!$this->timeouts->cancel((int)$input['id'])) {
            throw new RuntimeException('Таймер уже выполняется, завершён или отменён.');
        }
    }

    /** @return list<array{name:string,consumer:string}> */
    public function subscriptions(): array
    {
        $result = [];
        foreach (ScriptTriggers::catalog() as $name => $label) {
            $result[] = ['name' => $name, 'consumer' => $label];
        }
        foreach ($this->events->subscriptions() as $item) {
            $result[] = ['name' => $item['event'], 'consumer' => 'Модуль ' . $item['module'] . ' · обработчиков: ' . $item['handlers']];
        }
        foreach ($this->database->connection()->table('scripts')->whereNull('deleted_at')->where('event_name', '<>', '')
            ->orderBy('title')->get(['title', 'event_name']) as $script) {
            $result[] = ['name' => (string)$script->event_name, 'consumer' => 'Сценарий ' . (string)$script->title];
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    public function cycles(): array
    {
        $result = [];
        foreach ($this->supervisor->desired() as $process) {
            $result[] = $this->cycle($process['kind'], $process['id'], $process['tasks']);
        }

        return $result;
    }

    public function control(string $kind, string $id, string $operation): void
    {
        $targets = [];
        foreach ($this->cycles() as $cycle) {
            $targets[] = $cycle['kind'] . ':' . $cycle['id'];
        }
        $input = $this->validation->make(
            ['target' => $kind . ':' . $id, 'operation' => $operation],
            ['target' => ['required', Rule::in($targets)], 'operation' => ['required', Rule::in(['start', 'stop', 'restart'])]]
        )->validate();
        $action = ($kind === 'core' ? 'core.' : 'worker.') . $input['operation'];
        $this->agent->request(['action' => $action, 'module' => $id]);
        $key = 'runtime.manual_stop.' . $kind . '.' . $id;
        if ($operation === 'stop') {
            $this->cache->put($key, true);
        } else {
            $this->cache->forget($key);
        }
    }

    /**
     * @param list<string> $tasks
     * @return array<string, mixed>
     */
    private function cycle(string $kind, string $id, array $tasks): array
    {
        $module = $kind === 'core' ? 'core' : $id;
        $state = null;
        foreach ($tasks as $task) {
            $candidate = $this->states->get($module, $task);
            if ($candidate !== null && ($state === null || $candidate['updated_at'] > $state['updated_at'])) {
                $state = $candidate;
            }
        }
        $key = $kind === 'core' ? 'worker.lock.core.' . $id : 'worker.lock.' . $id;
        $pid = (int)($this->cache->get($key) ?? 0);
        $health = $this->cache->get('runtime.health.' . $kind . '.' . $id, []);
        $manual = $this->cache->get('runtime.manual_stop.' . $kind . '.' . $id) === true;
        $heartbeat = (int)$this->cache->get('worker.heartbeat.' . ($kind === 'core' ? 'core.' : '') . $id, 0);
        $error = (string)($state['error'] ?? '');
        $fault = !$manual && ($pid === 0 || $error !== '' || ($health['stale'] ?? false)
            || in_array($health['active'] ?? '', ['failed', 'inactive', 'activating'], true));
        return [
            'kind' => $kind, 'id' => $id, 'task' => implode(', ', $tasks), 'pid' => $pid,
            'updated_at' => $heartbeat > 0 ? date('Y-m-d H:i:s', $heartbeat) : '',
            'status' => $fault ? ($error !== '' ? 'Ошибка задачи' : 'Цикл не работает') : ($pid > 0 ? 'Работает' : 'Остановлен вручную'),
            'tone' => $fault ? 'danger' : ($pid > 0 ? 'success' : 'warning'), 'error' => $error,
            'next_run_at' => (string)($state['next_run_at'] ?? ''),
        ];
    }
}
