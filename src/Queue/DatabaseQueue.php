<?php

declare(strict_types=1);

namespace MajorDoMo\Queue;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use MajorDoMo\Database\DatabaseManager;
use RuntimeException;
use Throwable;

/**
 * Долговечная очередь в MariaDB/SQLite.
 *
 * Задание резервируется на время, чтобы упавший воркер не держал его вечно.
 * После исчерпания попыток оно остаётся в таблице с FAILED_AT и ошибкой —
 * это удобно для диагностики.
 */
final class DatabaseQueue implements Queue
{
    private readonly string $table;

    private readonly int $maxAttempts;

    private readonly int $retryDelay;

    private readonly int $reservationTimeout;

    /** @var Closure(): int */
    private readonly Closure $clock;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly DatabaseManager $database,
        array $config = [],
        ?Closure $clock = null
    ) {
        $this->table = (string)($config['table'] ?? 'queue_jobs');
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $this->table)) {
            throw new RuntimeException('Некорректное имя таблицы очереди.');
        }
        $this->maxAttempts = max(1, (int)($config['max_attempts'] ?? 3));
        $this->retryDelay = max(1, (int)($config['retry_delay'] ?? 60));
        $this->reservationTimeout = max(1, (int)($config['reservation_timeout'] ?? 300));
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function push(string $queue, string $job, array $payload = [], int $delaySeconds = 0): int
    {
        $this->assertName($queue, 'queue');
        $this->assertName($job, 'job');
        $this->ensureTable();
        $now = ($this->clock)();

        return (int)$this->database->connection()->table($this->table)->insertGetId([
            'QUEUE' => $queue,
            'JOB' => $job,
            'PAYLOAD' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
            'ATTEMPTS' => 0,
            'AVAILABLE_AT' => date('Y-m-d H:i:s', $now + max(0, $delaySeconds)),
            'RESERVED_AT' => null,
            'FAILED_AT' => null,
            'ERROR' => null,
            'CREATED_AT' => date('Y-m-d H:i:s', $now),
        ]);
    }

    public function pop(string $queue): ?QueueJob
    {
        $this->assertName($queue, 'queue');
        $this->ensureTable();
        $now = ($this->clock)();
        $reservationCutoff = date('Y-m-d H:i:s', $now - $this->reservationTimeout);
        $connection = $this->database->connection();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $row = $connection->table($this->table)
                ->where('QUEUE', $queue)
                ->whereNull('FAILED_AT')
                ->where('AVAILABLE_AT', '<=', date('Y-m-d H:i:s', $now))
                ->where(static function ($query) use ($reservationCutoff): void {
                    $query->whereNull('RESERVED_AT')
                        ->orWhere('RESERVED_AT', '<=', $reservationCutoff);
                })
                ->orderBy('ID')
                ->first();

            if ($row === null) {
                return null;
            }

            $reserved = $connection->table($this->table)
                ->where('ID', (int)$row->ID)
                ->whereNull('RESERVED_AT')
                ->update(['RESERVED_AT' => date('Y-m-d H:i:s', $now)]);

            if ($reserved === 1) {
                $payload = json_decode((string)$row->PAYLOAD, true);

                return new QueueJob(
                    (int)$row->ID,
                    (string)$row->QUEUE,
                    (string)$row->JOB,
                    is_array($payload) ? $payload : [],
                    (int)$row->ATTEMPTS
                );
            }
        }

        return null;
    }

    public function ack(QueueJob $job): void
    {
        $this->database->connection()->table($this->table)->where('ID', $job->id)->delete();
    }

    public function fail(QueueJob $job, Throwable $error): void
    {
        $attempts = $job->attempts + 1;
        $now = ($this->clock)();
        $values = [
            'ATTEMPTS' => $attempts,
            'RESERVED_AT' => null,
            'ERROR' => mb_substr($error->getMessage(), 0, 2000),
        ];

        if ($attempts >= $this->maxAttempts) {
            $values['FAILED_AT'] = date('Y-m-d H:i:s', $now);
        } else {
            $values['AVAILABLE_AT'] = date('Y-m-d H:i:s', $now + $this->retryDelay);
        }

        $this->database->connection()->table($this->table)->where('ID', $job->id)->update($values);
    }

    public function stats(string $queue): array
    {
        $this->assertName($queue, 'queue');
        $this->ensureTable();
        $now = ($this->clock)();
        $reservationCutoff = date('Y-m-d H:i:s', $now - $this->reservationTimeout);
        $table = $this->database->connection()->table($this->table)->where('QUEUE', $queue);

        $failed = (int)(clone $table)->whereNotNull('FAILED_AT')->count();
        $reserved = (int)(clone $table)
            ->whereNull('FAILED_AT')
            ->where('RESERVED_AT', '>', $reservationCutoff)
            ->count();
        $pending = (int)(clone $table)
            ->whereNull('FAILED_AT')
            ->where('AVAILABLE_AT', '<=', date('Y-m-d H:i:s', $now))
            ->where(static function ($query) use ($reservationCutoff): void {
                $query->whereNull('RESERVED_AT')
                    ->orWhere('RESERVED_AT', '<=', $reservationCutoff);
            })
            ->count();

        return ['pending' => $pending, 'reserved' => $reserved, 'failed' => $failed];
    }

    public function failed(string $queue, int $limit = 50): array
    {
        $this->assertName($queue, 'queue');
        $this->ensureTable();

        return $this->database->connection()->table($this->table)
            ->where('QUEUE', $queue)
            ->whereNotNull('FAILED_AT')
            ->orderByDesc('ID')
            ->limit(max(1, min(500, $limit)))
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->ID,
                'queue' => (string)$row->QUEUE,
                'job' => (string)$row->JOB,
                'attempts' => (int)$row->ATTEMPTS,
                'error' => (string)($row->ERROR ?? ''),
                'failed_at' => (string)$row->FAILED_AT,
            ])
            ->all();
    }

    public function retry(int $id): bool
    {
        $this->ensureTable();
        $now = ($this->clock)();

        return $this->database->connection()->table($this->table)
            ->where('ID', $id)
            ->whereNotNull('FAILED_AT')
            ->update([
                'ATTEMPTS' => 0,
                'FAILED_AT' => null,
                'ERROR' => null,
                'RESERVED_AT' => null,
                'AVAILABLE_AT' => date('Y-m-d H:i:s', $now),
            ]) > 0;
    }

    public function forget(int $id): bool
    {
        $this->ensureTable();

        return $this->database->connection()->table($this->table)
            ->where('ID', $id)
            ->whereNotNull('FAILED_AT')
            ->delete() > 0;
    }

    private function ensureTable(): void
    {
        $schema = $this->database->connection()->getSchemaBuilder();
        if ($schema->hasTable($this->table)) {
            return;
        }

        $schema->create($this->table, static function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('QUEUE', 64);
            $table->string('JOB', 191);
            $table->text('PAYLOAD');
            $table->integer('ATTEMPTS')->default(0);
            $table->dateTime('AVAILABLE_AT');
            $table->dateTime('RESERVED_AT')->nullable();
            $table->dateTime('FAILED_AT')->nullable();
            $table->text('ERROR')->nullable();
            $table->dateTime('CREATED_AT');
            $table->index(['QUEUE', 'FAILED_AT', 'AVAILABLE_AT']);
        });
    }

    private function assertName(string $value, string $label): void
    {
        if (!preg_match('/^[a-z][a-z0-9_.-]{1,190}$/D', $value)) {
            throw new RuntimeException('Некорректное имя ' . $label . ' очереди.');
        }
    }
}
