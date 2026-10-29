<?php

declare(strict_types=1);

namespace LaIOT\Services\Events;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use LaIOT\Database\DatabaseManager;
use RuntimeException;

/**
 * Долговечное состояние шагов автоматизации, отдельное от журнала событий.
 *
 * Шаги одной цепочки и команды одного свойства выполняются по порядку ID.
 * Короткие транзакции защищают только учёт и постановку доставки: пользовательский
 * PHP никогда не выполняется под блокировкой строки состояния.
 *
 * @phpstan-type Step array{id:int,root_id:int,parent_id:int,stream:string,status:string,failed_at:?string,error_step_id:int,message:string,event_id:int,handler_event_id:int,handler_done:bool,history_done:bool,delivery_done:bool}
 *
 * 2026-10-27 — состояние цепочек, контроль этапов и выбор готовых шагов.
 */
final class PropertyExecutionRepository
{
    private bool $ready = false;

    public function __construct(private readonly DatabaseManager $database)
    {
    }

    public function ensureTable(): void
    {
        if ($this->ready) {
            return;
        }
        $schema = $this->database->schema();
        if (!$schema->hasTable('property_executions')) {
            $schema->create('property_executions', self::schema(...));
        }
        $this->ready = true;
    }

    public static function schema(Blueprint $table): void
    {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('root_id')->default(0);
        $table->unsignedBigInteger('parent_id')->default(0);
        $table->string('stream', 191);
        $table->string('status', 16)->default('pending');
        $table->dateTime('failed_at')->nullable();
        $table->unsignedBigInteger('error_step_id')->default(0);
        $table->text('message')->nullable();
        $table->unsignedBigInteger('event_id')->default(0);
        $table->unsignedBigInteger('handler_event_id')->default(0);
        $table->boolean('handler_done')->default(false);
        $table->boolean('history_done')->default(false);
        $table->boolean('delivery_done')->default(false);
        $table->timestamps();
        $table->index(['root_id', 'status', 'id']);
        $table->index(['stream', 'status', 'id']);
        $table->index('created_at');
    }

    /** Вызывается в транзакции записи значения или постановки доставки. */
    public function create(int $parentId, string $stream): int
    {
        $this->ensureTable();
        $parent = $parentId > 0 ? $this->find($parentId) : null;
        $now = date('Y-m-d H:i:s');
        $id = (int)$this->table()->insertGetId([
            'root_id' => $parent['root_id'] ?? 0,
            'parent_id' => $parentId,
            'stream' => $stream,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ($parent === null) {
            $this->table()->where('id', $id)->update(['root_id' => $id]);
        }

        return $id;
    }

    /** @return Step */
    public function find(int $id): array
    {
        $row = $this->table()->where('id', $id)->first();
        if ($row === null) {
            throw new RuntimeException('Шаг автоматизации не найден: ' . $id);
        }

        return [
            'id' => (int)$row->id, 'root_id' => (int)$row->root_id,
            'parent_id' => (int)$row->parent_id, 'stream' => (string)$row->stream,
            'status' => (string)$row->status,
            'failed_at' => $row->failed_at === null ? null : (string)$row->failed_at,
            'error_step_id' => (int)$row->error_step_id, 'message' => (string)($row->message ?? ''),
            'event_id' => (int)$row->event_id, 'handler_event_id' => (int)$row->handler_event_id,
            'handler_done' => (bool)$row->handler_done, 'history_done' => (bool)$row->history_done,
            'delivery_done' => (bool)$row->delivery_done,
        ];
    }

    public function cancelled(int $id): bool
    {
        $step = $this->find($id);

        return $this->find($step['root_id'])['failed_at'] !== null;
    }

    /** @param array<string, int|string|bool|null> $attributes */
    public function update(int $id, array $attributes): void
    {
        $this->table()->where('id', $id)->update($attributes + ['updated_at' => date('Y-m-d H:i:s')]);
    }

    /** Ошибка отменяет ещё не выполненные действия всей цепочки. */
    public function fail(int $id, string $message): void
    {
        $step = $this->find($id);
        $this->database->transaction(function () use ($step, $id, $message): void {
            $this->table()->where('id', $step['root_id'])->whereNull('failed_at')->update([
                'failed_at' => date('Y-m-d H:i:s'), 'error_step_id' => $id,
                'message' => mb_substr($message, 0, 2000), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->update($id, ['status' => 'error', 'message' => mb_substr($message, 0, 2000)]);
        });
    }

    /** Квитанция этапа сохраняется атомарно с его короткими операциями в БД. */
    public function once(int $id, string $phase, callable $action): void
    {
        if (!in_array($phase, ['history_done', 'delivery_done'], true)) {
            throw new RuntimeException('Неизвестный этап автоматизации: ' . $phase);
        }
        $this->database->transaction(function () use ($id, $phase, $action): void {
            $row = $this->table()->where('id', $id)->lockForUpdate()->first();
            if ($row === null) {
                throw new RuntimeException('Шаг автоматизации не найден: ' . $id);
            }
            if (!(bool)$row->{$phase}) {
                $action();
                $this->update($id, [$phase => true]);
            }
        });
    }

    /** @param callable(): int $record */
    public function journal(int $id, string $column, callable $record): int
    {
        if (!in_array($column, ['event_id', 'handler_event_id'], true)) {
            throw new RuntimeException('Неизвестный указатель журнала: ' . $column);
        }

        return $this->database->transaction(function () use ($id, $column, $record): int {
            $step = $this->table()->where('id', $id)->lockForUpdate()->first();
            if ($step === null) {
                throw new RuntimeException('Шаг автоматизации не найден: ' . $id);
            }
            $eventId = (int)$step->{$column};
            if ($eventId === 0) {
                $eventId = $record();
                $this->update($id, [$column => $eventId]);
            }

            return $eventId;
        });
    }

    /** Очередь пропускает ожидающий шаг, но не обгоняет команды его свойства. */
    public static function constrainReady(Builder $query, string $queueTable): void
    {
        $query->leftJoin('property_executions as execution', $queueTable . '.execution_id', '=', 'execution.id')
            ->leftJoin('property_executions as execution_root', 'execution.root_id', '=', 'execution_root.id')
            ->where(static function (Builder $eligible) use ($queueTable): void {
                $eligible->whereNull($queueTable . '.execution_id')
                    ->orWhere(static function (Builder $managed): void {
                        $managed->whereNotNull('execution.id')->where(static function (Builder $state): void {
                            $state->whereNotNull('execution_root.failed_at')
                                ->orWhere(static function (Builder $ready): void {
                                    foreach (['root_id', 'stream'] as $key) {
                                        $ready->whereNotExists(static function (Builder $prior) use ($key): void {
                                            $prior->selectRaw('1')->from('property_executions as prior')
                                                ->whereColumn('prior.' . $key, 'execution.' . $key)
                                                ->whereColumn('prior.id', '<', 'execution.id')
                                                ->whereIn('prior.status', ['pending', 'running']);
                                        });
                                    }
                                });
                        });
                    });
            })->select($queueTable . '.*');
    }

    private function table(): Builder
    {
        return $this->database->connection()->table('property_executions');
    }
}
