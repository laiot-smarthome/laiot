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
 *
 * Изменения:
 * 2026-10-26 — просроченная резервация снова доступна после падения воркера.
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
            'queue' => $queue,
            'job' => $job,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
            'attempts' => 0,
            'available_at' => date('Y-m-d H:i:s', $now + max(0, $delaySeconds)),
            'reserved_at' => null,
            'failed_at' => null,
            'error' => null,
            'created_at' => date('Y-m-d H:i:s', $now),
            'updated_at' => date('Y-m-d H:i:s', $now),
        ]);
    }

    public function pushJob(Job $job, string $queue = 'default', int $delaySeconds = 0): int
    {
        return $this->push($queue, $job->name(), $job->payload(), $delaySeconds);
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
                ->where('queue', $queue)
                ->whereNull('deleted_at')
                ->whereNull('failed_at')
                ->where('available_at', '<=', date('Y-m-d H:i:s', $now))
                ->where(static function ($query) use ($reservationCutoff): void {
                    $query->whereNull('reserved_at')
                        ->orWhere('reserved_at', '<=', $reservationCutoff);
                })
                ->orderBy('id')
                ->first();

            if ($row === null) {
                return null;
            }

            $reserved = $connection->table($this->table)
                ->where('id', (int)$row->id)
                ->whereNull('deleted_at')
                ->whereNull('failed_at')
                ->where('available_at', '<=', date('Y-m-d H:i:s', $now))
                ->where(static function ($query) use ($reservationCutoff): void {
                    $query->whereNull('reserved_at')
                        ->orWhere('reserved_at', '<=', $reservationCutoff);
                })
                ->update([
                    'reserved_at' => date('Y-m-d H:i:s', $now),
                    'updated_at' => date('Y-m-d H:i:s', $now),
                ]);

            if ($reserved === 1) {
                $payload = json_decode((string)$row->payload, true);

                return new QueueJob(
                    (int)$row->id,
                    (string)$row->queue,
                    (string)$row->job,
                    is_array($payload) ? $payload : [],
                    (int)$row->attempts
                );
            }
        }

        return null;
    }

    public function ack(QueueJob $job): void
    {
        $this->database->connection()->table($this->table)->where('id', $job->id)->delete();
    }

    public function fail(QueueJob $job, Throwable $error): void
    {
        $attempts = $job->attempts + 1;
        $now = ($this->clock)();
        $values = [
            'attempts' => $attempts,
            'reserved_at' => null,
            'error' => mb_substr($error->getMessage(), 0, 2000),
            'updated_at' => date('Y-m-d H:i:s', $now),
        ];

        if ($attempts >= $this->maxAttempts) {
            $values['failed_at'] = date('Y-m-d H:i:s', $now);
        } else {
            $values['available_at'] = date('Y-m-d H:i:s', $now + $this->retryDelay);
        }

        $this->database->connection()->table($this->table)->where('id', $job->id)->update($values);
    }

    public function stats(string $queue): array
    {
        $this->assertName($queue, 'queue');
        $this->ensureTable();
        $now = ($this->clock)();
        $reservationCutoff = date('Y-m-d H:i:s', $now - $this->reservationTimeout);
        $table = $this->database->connection()->table($this->table)
            ->where('queue', $queue)
            ->whereNull('deleted_at');

        $failed = (int)(clone $table)->whereNotNull('failed_at')->count();
        $reserved = (int)(clone $table)
            ->whereNull('failed_at')
            ->where('reserved_at', '>', $reservationCutoff)
            ->count();
        $pending = (int)(clone $table)
            ->whereNull('failed_at')
            ->where('available_at', '<=', date('Y-m-d H:i:s', $now))
            ->where(static function ($query) use ($reservationCutoff): void {
                $query->whereNull('reserved_at')
                    ->orWhere('reserved_at', '<=', $reservationCutoff);
            })
            ->count();

        return ['pending' => $pending, 'reserved' => $reserved, 'failed' => $failed];
    }

    public function failed(string $queue, int $limit = 50): array
    {
        $this->assertName($queue, 'queue');
        $this->ensureTable();

        return $this->database->connection()->table($this->table)
            ->where('queue', $queue)
            ->whereNull('deleted_at')
            ->whereNotNull('failed_at')
            ->orderByDesc('id')
            ->limit(max(1, min(500, $limit)))
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'queue' => (string)$row->queue,
                'job' => (string)$row->job,
                'attempts' => (int)$row->attempts,
                'error' => (string)($row->error ?? ''),
                'failed_at' => (string)$row->failed_at,
            ])
            ->all();
    }

    /**
     * @return list<array{id:int,job:string,attempts:int,available_at:string}>
     */
    public function pending(string $queue, int $limit = 50): array
    {
        $this->assertName($queue, 'queue');
        $this->ensureTable();
        $now = ($this->clock)();
        $reservationCutoff = date('Y-m-d H:i:s', $now - $this->reservationTimeout);

        return $this->database->connection()->table($this->table)
            ->where('queue', $queue)
            ->whereNull('deleted_at')
            ->whereNull('failed_at')
            ->where('available_at', '<=', date('Y-m-d H:i:s', $now))
            ->where(static function ($query) use ($reservationCutoff): void {
                $query->whereNull('reserved_at')
                    ->orWhere('reserved_at', '<=', $reservationCutoff);
            })
            ->orderBy('id')
            ->limit(max(1, min(500, $limit)))
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'job' => (string)$row->job,
                'attempts' => (int)$row->attempts,
                'available_at' => (string)$row->available_at,
            ])
            ->all();
    }

    public function retry(int $id): bool
    {
        $this->ensureTable();
        $now = ($this->clock)();

        return $this->database->connection()->table($this->table)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->whereNotNull('failed_at')
            ->update([
                'attempts' => 0,
                'failed_at' => null,
                'error' => null,
                'reserved_at' => null,
                'available_at' => date('Y-m-d H:i:s', $now),
                'updated_at' => date('Y-m-d H:i:s', $now),
            ]) > 0;
    }

    public function forget(int $id): bool
    {
        $this->ensureTable();

        return $this->database->connection()->table($this->table)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->whereNotNull('failed_at')
            ->delete() > 0;
    }

    private function ensureTable(): void
    {
        $schema = $this->database->connection()->getSchemaBuilder();
        if ($schema->hasTable($this->table)) {
            return;
        }

        $schema->create($this->table, static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('queue', 64);
            $table->string('job', 191);
            $table->text('payload');
            $table->integer('attempts')->default(0);
            $table->dateTime('available_at');
            $table->dateTime('reserved_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['queue', 'failed_at', 'available_at']);
        });
    }

    private function assertName(string $value, string $label): void
    {
        if (!preg_match('/^[a-z][a-z0-9_.-]{1,190}$/D', $value)) {
            throw new RuntimeException('Некорректное имя ' . $label . ' очереди.');
        }
    }
}
