<?php

declare(strict_types=1);

namespace MajorDoMo\History;

use MajorDoMo\Database\DatabaseManager;

/**
 * История значений свойств.
 *
 * 2026-10-27 — время записи можно передать явно: строка истории пишется после
 *              OnChange, но должна нести время самого изменения.
 */
final readonly class HistoryRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    /**
     * @param string|null $at Время фактической записи значения; null — сейчас.
     *                        Нужен, потому что строка пишется после OnChange,
     *                        а время должно остаться временем изменения.
     */
    /**
     * Построитель выборки по истории одного свойства.
     */
    public function query(int $objectId, int $propertyId): HistoryQuery
    {
        return new HistoryQuery($this->database, $objectId, $propertyId);
    }

    public function record(int $objectId, int $propertyId, string $value, ?string $at = null): void
    {
        $now = $at ?? date('Y-m-d H:i:s');
        $this->database->connection()->table('property_history')->insert([
            'object_id' => $objectId,
            'property_id' => $propertyId,
            'value' => $value,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * @return list<array{id:int,object_id:int,object_title:string,property_id:int,property_title:string,value:string,created_at:string}>
     */
    public function all(string $search = '', int $limit = 200, int $objectId = 0): array
    {
        $query = $this->database->connection()->table('property_history')
            ->join('objects', 'objects.id', '=', 'property_history.object_id')
            ->join('properties', 'properties.id', '=', 'property_history.property_id')
            ->whereNull('property_history.deleted_at');
        if ($objectId > 0) {
            $query->where('property_history.object_id', $objectId);
        }
        if ($search !== '') {
            $query->where(static function ($query) use ($search): void {
                $query->where('objects.title', 'like', '%' . $search . '%')
                    ->orWhere('properties.title', 'like', '%' . $search . '%')
                    ->orWhere('property_history.value', 'like', '%' . $search . '%');
            });
        }

        return $query->orderByDesc('property_history.id')
            ->limit(max(1, min(500, $limit)))
            ->get([
                'property_history.*',
                'objects.title as object_title',
                'properties.title as property_title',
            ])
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'object_id' => (int)$row->object_id,
                'object_title' => (string)$row->object_title,
                'property_id' => (int)$row->property_id,
                'property_title' => (string)$row->property_title,
                'value' => (string)($row->value ?? ''),
                'created_at' => (string)$row->created_at,
            ])
            ->all();
    }

    /**
     * @return list<array{value:string,created_at:string}>
     */
    public function forValue(int $objectId, int $propertyId, int $limit = 50): array
    {
        return $this->database->connection()->table('property_history')
            ->where('object_id', $objectId)
            ->where('property_id', $propertyId)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit(max(1, min(500, $limit)))
            ->get()
            ->map(static fn (object $row): array => [
                'value' => (string)($row->value ?? ''),
                'created_at' => (string)$row->created_at,
            ])
            ->all();
    }

    /**
     * История одного объекта: все свойства, новые записи сверху.
     *
     * @return list<array{id:int,property_id:int,property_title:string,value:string,created_at:string}>
     */
    public function forObject(int $objectId, int $limit = 100): array
    {
        return $this->database->connection()->table('property_history')
            ->leftJoin('properties', 'properties.id', '=', 'property_history.property_id')
            ->where('property_history.object_id', $objectId)
            ->whereNull('property_history.deleted_at')
            ->orderByDesc('property_history.id')
            ->limit(max(1, min(500, $limit)))
            ->get([
                'property_history.id',
                'property_history.property_id',
                'property_history.value',
                'property_history.created_at',
                'properties.title as property_title',
            ])
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'property_id' => (int)$row->property_id,
                'property_title' => (string)($row->property_title ?? ''),
                'value' => (string)($row->value ?? ''),
                'created_at' => (string)$row->created_at,
            ])
            ->all();
    }

    public function delete(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('property_history')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }
}
