<?php

declare(strict_types=1);

namespace LaIOT\Services\Worker;

use LaIOT\Services\Events\PropertyEventQueue;
use LaIOT\Services\Events\SystemEventTicker;
use LaIOT\Services\Logging\ErrorRepository;
use LaIOT\Services\Logging\LoggerFactory;
use LaIOT\Services\Maintenance\RetentionPruner;
use LaIOT\Services\Queue\JobRegistry;
use LaIOT\Services\Queue\Queue;
use LaIOT\Services\Queue\QueueWorker;
use LaIOT\Services\Runtime\CodeRunner;
use LaIOT\Services\Runtime\TimeoutRepository;
use LaIOT\Services\WebSocket\WebSocketServer;
use LaIOT\Support\Clock;
use RuntimeException;
use Throwable;

/**
 * Критичные ядровые процессы: очередь, события свойств, setTimeOut и retention.
 *
 * 2026-10-30 — supervisor автоматически запускает и контролирует включённые циклы.
 * Они не делят процесс с модульными задачами и запускаются отдельными
 * systemd-юнитами `laiot-core@queue`/`laiot-core@events`/`laiot-core@timeouts`/
 * `laiot-core@maintenance`.
 * Очередь событий разбита на полосы: `core-worker events` обслуживает все,
 * `core-worker events.<n>` — одну, чтобы несколько процессов обрабатывали
 * независимые свойства параллельно, сохраняя порядок внутри свойства.
 *
 * 2026-10-29 — websocket является задачей ядра, использует общий unit/агент.
 * 2026-10-27 — отдельный module.<id> обслуживает привязки без задач модуля.
 * 2026-10-27 — системные события времени создаёт отдельный процесс.
 * 2026-10-27 — добавлен процесс events: OnChange и доставка свойства не
 *              выполняются в запросе, который записал значение.
 * 2026-10-27 — полосы событий: один процесс на полосу для параллельной работы.
 * 2026-10-27 — лог-каналы queue/events вместо общего app.
 * 2026-10-27 — блокировки полос удерживаются всё время жизни worker.
 * 2026-10-27 — maintenance: retention истории, журнала, очереди и ошибок.
 */
final class CoreWorker
{
    public const TASKS = ['queue', 'events', 'timeouts', 'maintenance', 'system-events', 'websocket', 'supervisor'];

    private bool $stop = false;

    public function __construct(
        private readonly Queue $queue,
        private readonly JobRegistry $jobs,
        private readonly TimeoutRepository $timeouts,
        private readonly CodeRunner $runner,
        private readonly BackgroundTaskState $state,
        private readonly PropertyEventQueue $events,
        private readonly LoggerFactory $loggers,
        private readonly ProcessLock $processLock,
        private readonly RetentionPruner $retention,
        private readonly SystemEventTicker $systemEvents,
        private readonly Clock $clock,
        private readonly ErrorRepository $errors,
        private readonly ?WebSocketServer $websocket = null,
        private readonly ?RuntimeSupervisor $supervisor = null
    ) {
    }

    public function run(string $task, bool $once = false): int
    {
        if ($task === 'websocket' && $once) {
            throw new RuntimeException('WebSocket — постоянная служба: --once для неё не поддерживается.');
        }
        $lane = $this->eventLane($task);
        $pid = getmypid() ?: 0;
        $keys = str_starts_with($task, 'events')
            ? array_map(static fn (string $queue): string => 'queue.' . $queue, $lane === null
                ? $this->events->queueNames() : [$this->events->queueName($lane)])
            : ['core.' . $task];
        if (str_starts_with($task, 'module.')) {
            $keys = ['queue.' . $task];
        }
        if (!$this->processLock->acquire($keys)) {
            throw new RuntimeException('Core-worker уже запущен: ' . $task);
        }
        try {
            if (!$this->state->acquireTask('core', $task, $pid)) {
                // ProcessLock уже наш: это остаток рестарта, не второй процесс.
                $this->state->takeoverTask('core', $task, $pid);
            }
            try {
                $this->installSignals();
                $this->state->start('core', $task, $pid, date('Y-m-d H:i:s'));
                try {
                    if ($task === 'queue') {
                        $this->runQueue('default', 'queue', $once, $pid);
                    } elseif (str_starts_with($task, 'module.')) {
                        $this->runQueue($task, $task, $once, $pid);
                    } elseif ($task === 'timeouts') {
                        $this->runTimeouts($once, $pid);
                    } elseif ($task === 'system-events') {
                        $this->runSystemEvents($once, $pid);
                    } elseif ($task === 'maintenance') {
                        $this->runMaintenance($once, $pid);
                    } elseif ($task === 'supervisor') {
                        $this->runSupervisor($once, $pid);
                    } elseif ($task === 'websocket') {
                        if ($this->websocket === null) {
                            throw new RuntimeException('Сервер WebSocket не зарегистрирован в контейнере.');
                        }
                        $this->websocket->run($pid);
                    } else {
                        $this->runEvents($lane, $task, $once, $pid);
                    }
                    $this->state->finish('core', $task, 'ok', null, date('Y-m-d H:i:s'));
                } catch (Throwable $error) {
                    $this->state->finish('core', $task, 'error', $error->getMessage(), date('Y-m-d H:i:s'));
                    throw $error;
                }
            } finally {
                $this->state->releaseTask('core', $task, $pid);
            }
        } finally {
            $this->processLock->release();
        }

        return 0;
    }

    /**
     * Номер полосы для `events.<n>`, null для «все полосы».
     */
    private function eventLane(string $task): ?int
    {
        if ($task === 'events') {
            return null;
        }
        if (preg_match('/^events\.(\d+)$/D', $task, $match) === 1) {
            $lane = (int)$match[1];
            if ($lane >= $this->events->lanes()) {
                throw new RuntimeException('Полоса событий вне диапазона: ' . $task);
            }

            return $lane;
        }
        if (in_array($task, self::TASKS, true)) {
            return null;
        }
        if (str_starts_with($task, 'module.')) {
            $this->events->moduleQueueName(substr($task, 7));
            return null;
        }

        throw new RuntimeException('Неизвестный core-worker: ' . $task);
    }

    private function runQueue(string $queue, string $task, bool $once, int $pid): void
    {
        $worker = new QueueWorker($this->queue, $this->jobs, $this->loggers->channel('queue'), $this->errors);
        while (!$this->stop) {
            $this->state->refreshTask('core', $task, $pid);
            $worked = $worker->work($queue);
            if ($once) {
                return;
            }
            if (!$worked) {
                usleep(100_000);
            }
        }
    }

    private function runSupervisor(bool $once, int $pid): void
    {
        if ($this->supervisor === null) {
            throw new RuntimeException('Supervisor не зарегистрирован в контейнере.');
        }
        do {
            $this->state->refreshTask('core', 'supervisor', $pid);
            try {
                $this->supervisor->tick();
            } catch (Throwable $error) {
                $this->errors->record('error', 'Ошибка контроля фоновых циклов', ['exception' => $error], '');
            }
            if ($once) {
                return;
            }
            for ($wait = 0; $wait < 50 && !$this->stop; $wait++) {
                usleep(100_000);
            }
        } while (!$this->stop);
    }

    /**
     * Обслуживание очередей событий.
     *
     * Без номера полосы процесс по кругу берёт по одному заданию из каждой
     * очереди; с номером — только из своей. Внутри полосы порядок сохраняется,
     * потому что её обслуживает один процесс.
     */
    private function runEvents(?int $lane, string $task, bool $once, int $pid): void
    {
        $queues = $lane === null
            ? $this->events->queueNames()
            : [$this->events->queueName($lane)];
        $worker = new QueueWorker($this->queue, $this->jobs, $this->loggers->channel('events'), $this->errors);

        while (!$this->stop) {
            $this->state->refreshTask('core', $task, $pid);
            $worked = false;
            foreach ($queues as $queue) {
                if (!$worker->work($queue)) {
                    continue;
                }
                $worked = true;
                if ($once) {
                    return;
                }
            }
            if ($once) {
                return;
            }
            if (!$worked) {
                usleep(100_000);
            }
        }
    }

    private function runTimeouts(bool $once, int $pid): void
    {
        while (!$this->stop) {
            $this->state->refreshTask('core', 'timeouts', $pid);
            foreach ($this->timeouts->claim() as $timeout) {
                try {
                    $this->runner->run($timeout['code']);
                    $this->timeouts->markExecuted($timeout['id'], $timeout['token']);
                } catch (Throwable $error) {
                    $this->timeouts->release($timeout['id'], $timeout['token']);
                    $this->loggers->channel('queue')->error('setTimeOut failed', [
                        'id' => $timeout['id'],
                        'error' => $error->getMessage(),
                    ]);
                }
            }
            if ($once) {
                return;
            }
            usleep(1_000_000);
        }
    }

    /**
     * Периодическая очистка по срокам из config/retention.php.
     *
     * Ошибка очистки не должна убивать сервис: пишем в queue-лог и
     * повторяем через interval. Процесс один на установку (ProcessLock).
     */
    private function runMaintenance(bool $once, int $pid): void
    {
        $next = 0;
        while (!$this->stop) {
            $this->state->refreshTask('core', 'maintenance', $pid);
            if (time() >= $next) {
                try {
                    $counts = $this->retention->prune();
                    $this->loggers->channel('queue')->info('Retention: очистка выполнена', $counts);
                } catch (Throwable $error) {
                    $this->loggers->channel('queue')->error('Retention: очистка не выполнена', [
                        'error' => $error->getMessage(),
                    ]);
                }
                if ($once) {
                    return;
                }
                $next = time() + $this->retention->interval();
            }
            sleep(1);
        }
    }

    private function runSystemEvents(bool $once, int $pid): void
    {
        while (!$this->stop) {
            $this->state->refreshTask('core', 'system-events', $pid);
            $this->systemEvents->tick($this->clock->now());
            if ($once) {
                return;
            }
            usleep(250_000);
        }
    }

    private function installSignals(): void
    {
        if (!function_exists('pcntl_async_signals') || !function_exists('pcntl_signal')) {
            return;
        }
        pcntl_async_signals(true);
        $stop = function (): void {
            $this->stop = true;
        };
        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);
    }
}
