<?php

declare(strict_types=1);

namespace MajorDoMo\Locations;

use InvalidArgumentException;
use MajorDoMo\Database\DatabaseManager;

/**
 * Локации MyDoMo: новая lowercase-таблица.
 */
final readonly class LocationRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    /**
     * @return list<array{id:int,title:string,priority:int}>
     */
    public function all(): array
    {
        return $this->database->connection()->table('locations')
            ->whereNull('deleted_at')
            ->orderByDesc('priority')
            ->orderBy('title')
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'title' => (string)$row->title,
                'priority' => (int)$row->priority,
            ])
            ->all();
    }

    /**
     * @return array{id:int,title:string,priority:int}|null
     */
    public function find(int $id): ?array
    {
        $row = $this->database->connection()->table('locations')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : [
            'id' => (int)$row->id,
            'title' => (string)$row->title,
            'priority' => (int)$row->priority,
        ];
    }

    public function create(string $title, int $priority = 0): int
    {
        $now = date('Y-m-d H:i:s');

        return (int)$this->database->connection()->table('locations')->insertGetId([
            'title' => $this->validateTitle($title),
            'priority' => $priority,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function update(int $id, string $title, int $priority): void
    {
        $this->database->connection()->table('locations')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update([
                'title' => $this->validateTitle($title),
                'priority' => $priority,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function delete(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('locations')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    private function validateTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 191) {
            throw new InvalidArgumentException('Название локации обязательно, до 191 символа.');
        }

        return $title;
    }
}
