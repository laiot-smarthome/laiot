<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\Events\PropertyEventQueue;
use MajorDoMo\Foundation\Application;

/**
 * Прогоняет очереди событий свойства до пуста.
 *
 * Запись значения асинхронна: `sg()`/`setValue()` только сохраняют значение и
 * ставят задание. Тест, который проверяет журнал, историю или подписчика,
 * должен явно выполнить `core-worker events` — здесь это делается в процессе
 * теста, по всем полосам очереди и без внешних сервисов.
 *
 * 2026-10-27 — помощник создан вместе с асинхронным конвейером свойств.
 * 2026-10-27 — учитывает полосы `events.<n>`; вложенные задания добираются
 *              следующим проходом.
 */
trait DrainsEvents
{
    private function drainEvents(Application $app, int $limit = 5000): int
    {
        $queue = $app->queue();
        $jobs = $app->jobs();
        $events = $app->container()->make(PropertyEventQueue::class);
        $handled = 0;

        do {
            $progress = 0;
            foreach ($events->queueNames() as $queueName) {
                while ($handled < $limit && ($job = $queue->pop($queueName)) !== null) {
                    $jobs->handle($job->job, $job->payload);
                    $queue->ack($job);
                    $handled++;
                    $progress++;
                }
            }
            foreach ($this->moduleQueues($app) as $queueName) {
                while ($handled < $limit && ($job = $queue->pop($queueName)) !== null) {
                    $jobs->handle($job->job, $job->payload);
                    $queue->ack($job);
                    $handled++;
                    $progress++;
                }
            }
        } while ($progress > 0 && $handled < $limit);

        return $handled;
    }

    /**
     * Очереди доставки, которые появились в БД: `module.<id>`.
     *
     * @return list<string>
     */
    private function moduleQueues(Application $app): array
    {
        $connection = $app->database()->connection();
        if (!$connection->getSchemaBuilder()->hasTable('queue_jobs')) {
            return [];
        }

        return $connection->table('queue_jobs')
            ->where('queue', 'like', 'module.%')
            ->distinct()
            ->orderBy('queue')
            ->pluck('queue')
            ->map(static fn (mixed $name): string => (string)$name)
            ->all();
    }

    private function pendingEvents(Application $app): int
    {
        return $app->container()->make(PropertyEventQueue::class)->pending();
    }
}
