<?php

declare(strict_types=1);

namespace MajorDoMo\Classes;

use InvalidArgumentException;
use MajorDoMo\Database\DatabaseManager;

/**
 * Классы объектов MyDoMo.
 */
final readonly class ClassRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    /**
     * @return list<array{id:int,title:string}>
     */
    public function all(): array
    {
        return $this->database->connection()->table('classes')
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get()
            ->map(static fn (object $row): array => ['id' => (int)$row->id, 'title' => (string)$row->title])
            ->all();
    }

    /**
     * @return array{id:int,title:string}|null
     */
    public function find(int $id): ?array
    {
        $row = $this->database->connection()->table('classes')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : ['id' => (int)$row->id, 'title' => (string)$row->title];
    }

    public function create(string $title): int
    {
        $now = date('Y-m-d H:i:s');

        return (int)$this->database->connection()->table('classes')->insertGetId([
            'title' => $this->title($title),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function update(int $id, string $title): void
    {
        $this->database->connection()->table('classes')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['title' => $this->title($title), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function delete(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('classes')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    private function title(string $title): string
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 191) {
            throw new InvalidArgumentException('Название класса обязательно, до 191 символа.');
        }

        return $title;
    }
}
