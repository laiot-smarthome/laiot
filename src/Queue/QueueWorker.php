<?php

declare(strict_types=1);

namespace MajorDoMo\Queue;

use MajorDoMo\Logging\ErrorRepository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Один шаг worker-а: взять задание, выполнить обработчик, подтвердить или
 * вернуть в очередь с ошибкой.
 *
 * 2026-10-27 — ошибки очереди доступны также в общем просмотре ошибок.
 */
final readonly class QueueWorker
{
    public function __construct(
        private Queue $queue,
        private JobRegistry $handlers,
        private LoggerInterface $logger,
        private ?ErrorRepository $errors = null
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
            // Успешная обработка не пишется в info: очередь событий обрабатывает
            // тысячи заданий, а журнал нужен для ошибок и диагностики.
            $this->logger->debug('Queue job handled', [
                'job' => $job->job,
                'queue' => $queue,
                'id' => $job->id,
            ]);
        } catch (Throwable $error) {
            $this->queue->fail($job, $error);
            $this->errors?->record('error', 'Ошибка очереди: ' . $error->getMessage(), [
                'exception' => $error, 'job' => $job->job, 'queue' => $queue,
                'job_id' => $job->id, 'execution_id' => $job->payload['execution_id'] ?? null,
            ], '');
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
