<?php

declare(strict_types=1);

namespace MajorDoMo\Maintenance;

use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Logging\ErrorRepository;
use Throwable;

/**
 * Retention: удаление устаревших данных по срокам из config/retention.php.
 *
 * История и журнал свойств растут на каждой записи значения, поэтому без
 * очистки база перестаёт быть предсказуемой. Очистка идёт порциями: длинная
 * транзакция на миллионы строк заблокировала бы запись значений и события.
 *
 * Срок `0` отключает очистку конкретного раздела. Ошибки удаляются вместе с
 * отдельными файлами; остальные таблицы чистятся физически.
 *
 * 2026-10-27 — очистка истории, журнала, очереди, таймеров и ошибок.
 */
final class RetentionPruner
{
    /** @var array<string, bool> */
    private array $tables = [];

    /**
     * @param array<string, mixed> $policy
     */
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly ErrorRepository $errors,
        private readonly array $policy
    ) {
    }

    /** Как часто `core-worker maintenance` повторяет очистку. */
    public function interval(): int
    {
        return max(300, (int)($this->policy['interval'] ?? 21600));
    }

    /**
     * @return array<string, int> Сколько строк удалено или будет удалено.
     */
    public function prune(bool $dryRun = false): array
    {
        $batch = max(100, min(50000, (int)($this->policy['batch'] ?? 5000)));

        return [
            'property_history' => $this->purgeByDate('property_history', $this->cutoff('history_days'), $batch, $dryRun),
            'property_events' => $this->purgeByDate('property_events', $this->cutoff('events_days'), $batch, $dryRun),
            'queue_jobs' => $this->purgeQueue($this->cutoff('queue_days'), $batch, $dryRun),
            'runtime_timeouts' => $this->purgeTimeouts($this->cutoff('timeouts_days'), $batch, $dryRun),
            'system_errors' => $this->purgeErrors($this->cutoff('errors_days'), $batch, $dryRun),
        ];
    }

    private function cutoff(string $key): ?string
    {
        $days = (int)($this->policy[$key] ?? 0);
        if ($days <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', time() - $days * 86400);
    }

    private function purgeByDate(string $table, ?string $cutoff, int $batch, bool $dryRun): int
    {
        if ($cutoff === null || !$this->table($table)) {
            return 0;
        }
        if ($dryRun) {
            return (int)$this->database->connection()->table($table)->where('created_at', '<', $cutoff)->count();
        }

        $total = 0;
        do {
            $ids = $this->database->connection()->table($table)
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batch)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int)$id)
                ->all();
            if ($ids === []) {
                break;
            }
            $total += (int)$this->database->connection()->table($table)->whereIn('id', $ids)->delete();
        } while (count($ids) === $batch);

        return $total;
    }

    /**
     * В очереди чистятся только окончательно упавшие задания: ожидающие
     * и повторяемые строки ещё нужны worker'у.
     */
    private function purgeQueue(?string $cutoff, int $batch, bool $dryRun): int
    {
        if ($cutoff === null || !$this->table('queue_jobs')) {
            return 0;
        }
        if ($dryRun) {
            return (int)$this->database->connection()->table('queue_jobs')
                ->whereNotNull('failed_at')
                ->where('failed_at', '<', $cutoff)
                ->count();
        }

        $total = 0;
        do {
            $ids = $this->database->connection()->table('queue_jobs')
                ->whereNotNull('failed_at')
                ->where('failed_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batch)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int)$id)
                ->all();
            if ($ids === []) {
                break;
            }
            $total += (int)$this->database->connection()->table('queue_jobs')->whereIn('id', $ids)->delete();
        } while (count($ids) === $batch);

        return $total;
    }

    /**
     * Таймеры удаляются только после выполнения или явной отмены: старый, но
     * ещё не выполненный setTimeOut должен дождаться worker'а.
     */
    private function purgeTimeouts(?string $cutoff, int $batch, bool $dryRun): int
    {
        if ($cutoff === null || !$this->table('runtime_timeouts')) {
            return 0;
        }
        $filter = static function ($query) use ($cutoff): void {
            $query->where('executed_at', '<', $cutoff)
                ->orWhere('deleted_at', '<', $cutoff);
        };
        if ($dryRun) {
            return (int)$this->database->connection()->table('runtime_timeouts')->where($filter)->count();
        }

        $total = 0;
        do {
            $ids = $this->database->connection()->table('runtime_timeouts')
                ->where($filter)
                ->orderBy('id')
                ->limit($batch)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int)$id)
                ->all();
            if ($ids === []) {
                break;
            }
            $total += (int)$this->database->connection()->table('runtime_timeouts')->whereIn('id', $ids)->delete();
        } while (count($ids) === $batch);

        return $total;
    }

    private function purgeErrors(?string $cutoff, int $batch, bool $dryRun): int
    {
        if ($cutoff === null) {
            return 0;
        }

        return $dryRun ? $this->errors->countBefore($cutoff) : $this->errors->purgeBefore($cutoff, $batch);
    }

    private function table(string $table): bool
    {
        if (array_key_exists($table, $this->tables)) {
            return $this->tables[$table];
        }

        try {
            $exists = $this->database->connection()->getSchemaBuilder()->hasTable($table);
        } catch (Throwable) {
            $exists = false;
        }

        return $this->tables[$table] = $exists;
    }
}
