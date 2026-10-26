<?php

declare(strict_types=1);

namespace MajorDoMo\Queue;

use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Один шаг worker-а: взять задание, выполнить обработчик, подтвердить или
 * вернуть в очередь с ошибкой.
 */
final readonly class QueueWorker
{
    public function __construct(
        private Queue $queue,
        private JobRegistry $handlers,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @return bool true, если задание было взято (успешно или с ошибкой)
     */
    public function work(string $queue = 'default'): bool
    {
        $job = $this->queue->pop($queue);
        if ($job === null) {
            return false;
        }

        try {
            $this->handlers->handle($job->job, $job->payload);
            $this->queue->ack($job);
            $this->logger->info('Queue job handled', [
                'job' => $job->job,
                'queue' => $queue,
                'id' => $job->id,
            ]);
        } catch (Throwable $error) {
            $this->queue->fail($job, $error);
            $this->logger->error('Queue job failed', [
                'job' => $job->job,
                'queue' => $queue,
                'id' => $job->id,
                'attempt' => $job->attempts + 1,
                'exception' => $error,
            ]);
        }

        return true;
    }
}
