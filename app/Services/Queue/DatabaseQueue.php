<?php

declare(strict_types=1);

namespace LaIOT\Services\Queue;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Events\PropertyEventRepository;
use LaIOT\Services\Events\PropertyExecutionRepository;
use LaIOT\Services\Logging\ErrorRepository;
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
 * 2026-10-27 — готовность цепочки и порядок свойства проверяются при выдаче.
 * 2026-10-27 — полоса событий ждёт повтор старшего задания, сохраняя порядок.
 * 2026-10-27 — подтверждение и ошибка относятся к конкретной резервации.
 * 2026-10-27 — проверка таблицы кэшируется: очередь событий вызывает её на
 *              каждом задании.
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

    /** Таблица проверяется один раз на процесс: лишний запрос к схеме на каждой операции не нужен. */
    private bool $tableReady = false;

    private ?bool $hasExecutions = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly DatabaseManager $database,
        array $config = [],
        ?Closure $clock = null,
        private readonly ?ErrorRepository $errors = null,
        private readonly ?PropertyEventRepository $eventLog = null
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
        $executionId = in_array($job, ['property.changed', 'module.property.changed'], true)
            ? max(0, (int)($payload['execution_id'] ?? 0)) : 0;
        if ($executionId > 0) {
            $this->hasExecutions = true;
        }

        return (int)$this->database->connection()->table($this->table)->insertGetId([
            'queue' => $queue,
            'job' => $job,
            'execution_id' => $executionId > 0 ? $executionId : null,
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
        if ($this->hasExecutions !== true) {
            $this->hasExecutions = $connection->getSchemaBuilder()->hasTable('property_executions');
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $orderedEvents = !$this->hasExecutions
                && ($queue === 'events' || preg_match('/^events\.\d+$/D', $queue) === 1);
            $query = $connection->table($this->table)
                ->where($this->table . '.queue', $queue)
                ->whereNull($this->table . '.deleted_at')
                ->whereNull($this->table . '.failed_at');
            if (!$orderedEvents) {
                $query->where($this->table . '.available_at', '<=', date('Y-m-d H:i:s', $now))
                    ->where(static function ($query) use ($reservationCutoff): void {
                        $query->whereNull('reserved_at')
                            ->orWhere('reserved_at', '<=', $reservationCutoff);
                    });
            }
            if ($this->hasExecutions) {
                PropertyExecutionRepository::constrainReady($query, $this->table);
            }
            $row = $query->orderBy($this->table . '.id')->first();

            if ($row === null) {
                return null;
            }
            if ($orderedEvents && ((string)$row->available_at > date('Y-m-d H:i:s', $now)
                || ($row->reserved_at !== null && (string)$row->reserved_at > $reservationCutoff))) {
                return null;
            }

            $reservationToken = bin2hex(random_bytes(16));
            // Просроченная резервация означает аварийно оборванную попытку.
            // Иначе exit/OOM в пользовательском PHP повторялся бы бесконечно.
            $attempts = (int)$row->attempts + ($row->reserved_at !== null ? 1 : 0);
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
                    'reservation_token' => $reservationToken,
                    'attempts' => $attempts,
                    'updated_at' => date('Y-m-d H:i:s', $now),
                ]);

            if ($reserved === 1) {
                $payload = json_decode((string)$row->payload, true);

                $job = new QueueJob(
                    (int)$row->id,
                    (string)$row->queue,
                    (string)$row->job,
                    is_array($payload) ? $payload : [],
                    $attempts,
                    $reservationToken
                );
                if ($attempts >= $this->maxAttempts) {
                    $error = new RuntimeException('Превышен лимит аварийных завершений задания.');
                    $this->fail($job, $error);
                    $this->errors?->record('error', $error->getMessage(), [
                        'exception' => $error, 'job_id' => $job->id,
                        'execution_id' => $job->payload['execution_id'] ?? null,
                    ], '');
                    continue;
                }

                return $job;
            }
        }

        return null;
    }

    public function ack(QueueJob $job): void
    {
        $this->database->connection()->table($this->table)->where('id', $job->id)
            ->where('reservation_token', $job->reservationToken)->delete();
    }

    public function fail(QueueJob $job, Throwable $error): void
    {
        $attempts = min($this->maxAttempts, $job->attempts + 1);
        $now = ($this->clock)();
        $values = [
            'attempts' => $attempts,
            'reserved_at' => null,
            'reservation_token' => null,
            'error' => mb_substr($error->getMessage(), 0, 2000),
            'updated_at' => date('Y-m-d H:i:s', $now),
        ];

        if ($attempts >= $this->maxAttempts) {
            $values['failed_at'] = date('Y-m-d H:i:s', $now);
        } else {
            $values['available_at'] = date('Y-m-d H:i:s', $now + $this->retryDelay);
        }

        $this->database->transaction(function () use ($job, $values, $attempts, $error): void {
            $updated = $this->database->connection()->table($this->table)->where('id', $job->id)
                ->where('reservation_token', $job->reservationToken)->update($values);
            $executionId = (int)($job->payload['execution_id'] ?? 0);
            if ($updated === 1 && $attempts >= $this->maxAttempts && $executionId > 0) {
                $states = new PropertyExecutionRepository($this->database);
                $states->fail($executionId, $error->getMessage());
                $step = $states->find($executionId);
                $this->eventLog?->finish($step['event_id'], 'error', $error->getMessage());
                if (!$step['handler_done']) {
                    $this->eventLog?->finish($step['handler_event_id'], 'error', $error->getMessage());
                }
            }
        });
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

        return $this->database->transaction(function () use ($id, $now): bool {
            $query = $this->database->connection()->table($this->table)->where('id', $id)
                ->whereNull('deleted_at')->whereNotNull('failed_at');
            $row = (clone $query)->lockForUpdate()->first();
            if ($row === null) {
                return false;
            }
            $execution = [];
            if ((int)($row->execution_id ?? 0) > 0) {
                $states = new PropertyExecutionRepository($this->database);
                $previous = $states->find((int)$row->execution_id);
                $newId = $states->create(0, $previous['stream']);
                // Ручной повтор — новая цепочка: ранее отменённые потомки
                // остаются отменёнными, учтённая история не дублируется.
                $states->update($newId, ['history_done' => $previous['history_done']]);
                $payload = json_decode((string)$row->payload, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($payload)) {
                    throw new RuntimeException('Payload задания должен быть массивом.');
                }
                $payload['execution_id'] = $newId;
                $execution = [
                    'execution_id' => $newId,
                    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ];
            }

            return $query->update($execution + [
                'attempts' => 0,
                'failed_at' => null,
                'error' => null,
                'reserved_at' => null,
                'reservation_token' => null,
                'available_at' => date('Y-m-d H:i:s', $now),
                'updated_at' => date('Y-m-d H:i:s', $now),
            ]) > 0;
        });
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
        if ($this->tableReady) {
            return;
        }

        $schema = $this->database->connection()->getSchemaBuilder();
        if ($schema->hasTable($this->table)) {
            $this->tableReady = true;

            return;
        }

        $schema->create($this->table, static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('queue', 64);
            $table->string('job', 191);
            $table->unsignedBigInteger('execution_id')->nullable()->index();
            $table->text('payload');
            $table->integer('attempts')->default(0);
            $table->dateTime('available_at');
            $table->dateTime('reserved_at')->nullable();
            $table->string('reservation_token', 32)->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['queue', 'failed_at', 'available_at']);
        });
        $this->tableReady = true;
    }

    private function assertName(string $value, string $label): void
    {
        if (!preg_match('/^[a-z][a-z0-9_.-]{1,190}$/D', $value)) {
            throw new RuntimeException('Некорректное имя ' . $label . ' очереди.');
        }
    }
}
