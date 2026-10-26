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
