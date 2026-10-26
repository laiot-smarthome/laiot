<?php

declare(strict_types=1);

namespace MajorDoMo\Worker;

use MajorDoMo\Queue\JobRegistry;
use MajorDoMo\Queue\Queue;
use MajorDoMo\Queue\QueueWorker;
use MajorDoMo\Runtime\CodeRunner;
use MajorDoMo\Runtime\TimeoutRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Критичные ядровые процессы: очередь и setTimeOut.
 *
 * Они не делят процесс с модульными задачами и запускаются отдельными
 * systemd-юнитами `mydomo-core@queue`/`mydomo-core@timeouts`.
 */
final class CoreWorker
{
    public const TASKS = ['queue', 'timeouts'];

    private bool $stop = false;

    public function __construct(
        private readonly Queue $queue,
        private readonly JobRegistry $jobs,
        private readonly TimeoutRepository $timeouts,
        private readonly CodeRunner $runner,
        private readonly BackgroundTaskState $state,
        private readonly LoggerInterface $logger
    ) {
    }

    public function run(string $task, bool $once = false): int
    {
        if (!in_array($task, self::TASKS, true)) {
            throw new RuntimeException('Неизвестный core-worker: ' . $task);
        }
        $pid = getmypid() ?: 0;
        if (!$this->state->acquireTask('core', $task, $pid)) {
            throw new RuntimeException('Core-worker уже запущен: ' . $task);
        }
        $this->installSignals();
        $this->state->start('core', $task, $pid, date('Y-m-d H:i:s'));
        try {
            if ($task === 'queue') {
                $this->runQueue($once, $pid);
            } else {
                $this->runTimeouts($once, $pid);
            }
            $this->state->finish('core', $task, 'ok', null, date('Y-m-d H:i:s'));
        } catch (Throwable $error) {
            $this->state->finish('core', $task, 'error', $error->getMessage(), date('Y-m-d H:i:s'));
            throw $error;
        } finally {
            $this->state->releaseTask('core', $task, $pid);
        }

        return 0;
    }

    private function runQueue(bool $once, int $pid): void
    {
        $worker = new QueueWorker($this->queue, $this->jobs, $this->logger);
        while (!$this->stop) {
            $this->state->refreshTask('core', 'queue', $pid);
            $worked = $worker->work('default');
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
            foreach ($this->timeouts->due() as $timeout) {
                try {
                    $this->runner->run($timeout['code']);
                    $this->timeouts->markExecuted($timeout['id']);
                } catch (Throwable $error) {
                    $this->logger->error('setTimeOut failed', [
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
