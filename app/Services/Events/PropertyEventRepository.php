<?php

declare(strict_types=1);

namespace LaIOT\Services\Events;

use LaIOT\Database\DatabaseManager;
use LaIOT\Support\Clock;
use Throwable;

/**
 * Журнал цепочки событий свойства (`property_events`).
 *
 * Пишет только сервис `PropertyValueService`: одна строка — один шаг
 * «значение изменилось» или «вызван OnChange-обработчик». Журнал не является
 * источником данных для автоматизации, поэтому его недоступность не должна
 * ломать запись свойства.
 *
 * 2026-10-27 — проверка наличия таблицы кэшируется на процесс: журнал пишется
 *              на каждое событие.
 * 2026-10-26 — таблица и репозиторий созданы для нового ядра LaIOT.
 */
final class PropertyEventRepository
{
    private const MAX_MESSAGE = 2000;

    /**
     * Проверка таблицы запоминается: журнал пишется на каждое событие, а
     * лишний запрос к схеме на горячем пути не нужен.
     */
    private ?bool $tableExists = null;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly Clock $clock
    ) {
    }

    public function hasTable(): bool
    {
        if ($this->tableExists !== null) {
            return $this->tableExists;
        }

        try {
            $this->tableExists = $this->database->connection()->getSchemaBuilder()->hasTable('property_events');
        } catch (Throwable) {
            $this->tableExists = false;
        }

        return $this->tableExists;
    }

    /**
     * @param array{
     *     parent_id?:int,
     *     type:string,
     *     status:string,
     *     source?:string,
     *     object_id?:int,
     *     property_id?:int,
     *     object_title?:string,
     *     property_title?:string,
     *     old_value?:string|null,
     *     new_value?:string|null,
     *     handler?:string,
     *     message?:string|null,
     *     chain?:list<string>,
     *     depth?:int
     * } $attributes
     * @return int id строки журнала или 0, если таблицы ещё нет
     */
    public function record(array $attributes): int
    {
        if (!$this->hasTable()) {
            return 0;
        }

        $now = $this->clock->nowString();

        try {
            return (int)$this->database->connection()->table('property_events')->insertGetId([
                'parent_id' => max(0, (int)($attributes['parent_id'] ?? 0)),
                'type' => mb_substr((string)$attributes['type'], 0, 32),
                'status' => mb_substr((string)$attributes['status'], 0, 32),
                'source' => mb_substr((string)($attributes['source'] ?? ''), 0, 191),
                'object_id' => max(0, (int)($attributes['object_id'] ?? 0)),
                'property_id' => max(0, (int)($attributes['property_id'] ?? 0)),
                'object_title' => mb_substr((string)($attributes['object_title'] ?? ''), 0, 191),
                'property_title' => mb_substr((string)($attributes['property_title'] ?? ''), 0, 191),
                'old_value' => $attributes['old_value'] ?? null,
                'new_value' => $attributes['new_value'] ?? null,
                'handler' => mb_substr((string)($attributes['handler'] ?? ''), 0, 191),
                'message' => $this->message($attributes['message'] ?? null),
                'chain' => $this->chain($attributes['chain'] ?? []),
                'depth' => max(0, (int)($attributes['depth'] ?? 0)),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        } catch (Throwable) {
            // Журнал — диагностика: сбой записи не должен откатывать значение.
            return 0;
        }
    }

    /**
     * Финализирует строку обработчика, созданную до запуска пользовательского метода.
     */
    public function finish(int $id, string $status, string $message = ''): void
    {
        if ($id <= 0 || !$this->hasTable()) {
            return;
        }

        try {
            $this->database->connection()->table('property_events')
                ->where('id', $id)
                ->update([
                    'status' => mb_substr($status, 0, 32),
                    'message' => $this->message($message),
                    'updated_at' => $this->clock->nowString(),
                ]);
        } catch (Throwable) {
            // Статус обработчика — тоже диагностика, а не часть записи значения.
        }
    }

    /**
     * @return list<array{
     *     id:int,
     *     parent_id:int,
     *     type:string,
     *     status:string,
     *     source:string,
     *     object_id:int,
     *     property_id:int,
     *     object_title:string,
     *     property_title:string,
     *     old_value:string,
     *     new_value:string,
     *     handler:string,
     *     message:string,
     *     chain:list<string>,
     *     depth:int,
     *     created_at:string
     * }>
     */
    public function latest(int $limit = 200, string $search = '', string $type = '', string $status = ''): array
    {
        if (!$this->hasTable()) {
            return [];
        }

        $query = $this->database->connection()->table('property_events')->whereNull('deleted_at');
        if ($type !== '') {
            $query->where('type', $type);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($search !== '') {
            $query->where(static function ($query) use ($search): void {
                $query->where('object_title', 'like', '%' . $search . '%')
                    ->orWhere('property_title', 'like', '%' . $search . '%')
                    ->orWhere('handler', 'like', '%' . $search . '%')
                    ->orWhere('message', 'like', '%' . $search . '%')
                    ->orWhere('old_value', 'like', '%' . $search . '%')
                    ->orWhere('new_value', 'like', '%' . $search . '%');
            });
        }

        return $query->orderByDesc('id')
            ->limit(max(1, min(500, $limit)))
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'parent_id' => (int)$row->parent_id,
                'type' => (string)$row->type,
                'status' => (string)$row->status,
                'source' => (string)$row->source,
                'object_id' => (int)$row->object_id,
                'property_id' => (int)$row->property_id,
                'object_title' => (string)$row->object_title,
                'property_title' => (string)$row->property_title,
                'old_value' => (string)($row->old_value ?? ''),
                'new_value' => (string)($row->new_value ?? ''),
                'handler' => (string)$row->handler,
                'message' => (string)($row->message ?? ''),
                'chain' => self::decodeChain($row->chain ?? null),
                'depth' => (int)$row->depth,
                'created_at' => (string)$row->created_at,
            ])
            ->all();
    }

    /**
     * Журнал цепочки одного объекта.
     *
     * @return list<array{id:int,type:string,status:string,property_title:string,old_value:string,new_value:string,handler:string,message:string,created_at:string}>
     */
    public function forObject(int $objectId, int $limit = 100): array
    {
        if (!$this->hasTable()) {
            return [];
        }

        return $this->database->connection()->table('property_events')
            ->where('object_id', $objectId)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit(max(1, min(500, $limit)))
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'type' => (string)$row->type,
                'status' => (string)$row->status,
                'property_title' => (string)$row->property_title,
                'old_value' => (string)($row->old_value ?? ''),
                'new_value' => (string)($row->new_value ?? ''),
                'handler' => (string)$row->handler,
                'message' => (string)($row->message ?? ''),
                'created_at' => (string)$row->created_at,
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return $this->distinct('type');
    }

    /**
     * @return list<string>
     */
    public function statuses(): array
    {
        return $this->distinct('status');
    }

    /**
     * @return list<string>
     */
    private function distinct(string $column): array
    {
        if (!$this->hasTable()) {
            return [];
        }

        return $this->database->connection()->table('property_events')
            ->whereNull('deleted_at')
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(static fn (mixed $value): string => (string)$value)
            ->all();
    }

    /**
     * @param list<string> $chain
     */
    private function chain(array $chain): string
    {
        $chain = array_values(array_filter($chain, static fn (string $token): bool => $token !== ''));

        return (string)json_encode($chain, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return list<string>
     */
    private static function decodeChain(?string $chain): array
    {
        if ($chain === null || $chain === '') {
            return [];
        }

        $decoded = json_decode($chain, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn (mixed $token): bool => is_string($token) && $token !== ''));
    }

    private function message(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return null;
        }

        return mb_substr($message, 0, self::MAX_MESSAGE);
    }
}
