<?php

declare(strict_types=1);

namespace MajorDoMo\Properties;

use MajorDoMo\Database\DatabaseManager;

/**
 * Значения свойств объектов.
 */
final readonly class ValueRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    /**
     * @return array<int,string> property_id => value
     */
    public function forObject(int $objectId): array
    {
        return $this->database->connection()->table('property_values')
            ->where('object_id', $objectId)
            ->whereNull('deleted_at')
            ->pluck('value', 'property_id')
            ->map(static fn (mixed $value): string => (string)($value ?? ''))
            ->all();
    }

    /**
     * @return list<array{id:int,object_id:int,object_title:string,property_id:int,property_title:string,value:string,updated_at:string}>
     */
    public function all(string $search = ''): array
    {
        $query = $this->database->connection()->table('property_values')
            ->join('objects', 'objects.id', '=', 'property_values.object_id')
            ->join('properties', 'properties.id', '=', 'property_values.property_id')
            ->whereNull('property_values.deleted_at');
        if ($search !== '') {
            $query->where(static function ($query) use ($search): void {
                $query->where('objects.title', 'like', '%' . $search . '%')
                    ->orWhere('properties.title', 'like', '%' . $search . '%')
                    ->orWhere('property_values.value', 'like', '%' . $search . '%');
            });
        }

        return $query->orderBy('objects.title')
            ->orderBy('properties.title')
            ->get([
                'property_values.*',
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
                'updated_at' => (string)$row->updated_at,
            ])
            ->all();
    }

    public function updateValue(int $id, string $value): void
    {
        $this->database->connection()->table('property_values')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function delete(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('property_values')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    public function get(int $objectId, int $propertyId): ?string
    {
        $value = $this->database->connection()->table('property_values')
            ->where('object_id', $objectId)
            ->where('property_id', $propertyId)
            ->whereNull('deleted_at')
            ->value('value');

        return $value === null ? null : (string)$value;
    }

    public function set(int $objectId, int $propertyId, string $value): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('property_values')->updateOrInsert(
            ['object_id' => $objectId, 'property_id' => $propertyId],
            ['value' => $value, 'updated_at' => $now, 'created_at' => $now, 'deleted_at' => null]
        );
    }
}
