<?php

declare(strict_types=1);

namespace MajorDoMo\Worker;

use MajorDoMo\Events\PropertyEventQueue;
use MajorDoMo\Logging\LoggerFactory;
use MajorDoMo\Maintenance\RetentionPruner;
use MajorDoMo\Queue\JobRegistry;
use MajorDoMo\Queue\Queue;
use MajorDoMo\Queue\QueueWorker;
use MajorDoMo\Runtime\CodeRunner;
use MajorDoMo\Runtime\TimeoutRepository;
use RuntimeException;
use Throwable;

/**
 * Критичные ядровые процессы: очередь, события свойств, setTimeOut и retention.
 *
 * Они не делят процесс с модульными задачами и запускаются отдельными
 * systemd-юнитами `mydomo-core@queue`/`mydomo-core@events`/`mydomo-core@timeouts`/
 * `mydomo-core@maintenance`.
 * Очередь событий разбита на полосы: `core-worker events` обслуживает все,
 * `core-worker events.<n>` — одну, чтобы несколько процессов обрабатывали
 * независимые свойства параллельно, сохраняя порядок внутри свойства.
 *
 * 2026-10-27 — добавлен процесс events: OnChange и доставка свойства не
 *              выполняются в запросе, который записал значение.
 * 2026-10-27 — полосы событий: один процесс на полосу для параллельной работы.
 * 2026-10-27 — лог-каналы queue/events вместо общего app.
 * 2026-10-27 — блокировки полос удерживаются всё время жизни worker.
 * 2026-10-27 — maintenance: retention истории, журнала, очереди и ошибок.
 */
final class CoreWorker
{
    public const TASKS = ['queue', 'events', 'timeouts', 'maintenance'];

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
        private readonly RetentionPruner $retention
    ) {
    }

    public function run(string $task, bool $once = false): int
    {
        $lane = $this->eventLane($task);
        $pid = getmypid() ?: 0;
        $keys = str_starts_with($task, 'events')
            ? array_map(static fn (string $queue): string => 'queue.' . $queue, $lane === null
                ? $this->events->queueNames() : [$this->events->queueName($lane)])
            : ['core.' . $task];
        if (!$this->processLock->acquire($keys)) {
            throw new RuntimeException('Core-worker уже запущен: ' . $task);
        }
        try {
            if (!$this->state->acquireTask('core', $task, $pid)) {
                throw new RuntimeException('Core-worker уже запущен: ' . $task);
            }
            try {
                $this->installSignals();
                $this->state->start('core', $task, $pid, date('Y-m-d H:i:s'));
                try {
                    if ($task === 'queue') {
                        $this->runQueue('default', 'queue', $once, $pid);
                    } elseif ($task === 'timeouts') {
                        $this->runTimeouts($once, $pid);
                    } elseif ($task === 'maintenance') {
                        $this->runMaintenance($once, $pid);
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

        throw new RuntimeException('Неизвестный core-worker: ' . $task);
    }

    private function runQueue(string $queue, string $task, bool $once, int $pid): void
    {
        $worker = new QueueWorker($this->queue, $this->jobs, $this->loggers->channel('queue'));
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
        $worker = new QueueWorker($this->queue, $this->jobs, $this->loggers->channel('events'));

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
