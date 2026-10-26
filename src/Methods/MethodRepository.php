<?php

declare(strict_types=1);

namespace MajorDoMo\Methods;

use InvalidArgumentException;
use MajorDoMo\Database\DatabaseManager;

/**
 * Методы классов MyDoMo.
 */
final readonly class MethodRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    /**
     * @return list<array{id:int,class_id:int,title:string,description:string}>
     */
    public function all(): array
    {
        return $this->database->connection()->table('methods')
            ->whereNull('deleted_at')
            ->orderBy('class_id')
            ->orderBy('title')
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'class_id' => (int)$row->class_id,
                'title' => (string)$row->title,
                'description' => (string)($row->description ?? ''),
            ])
            ->all();
    }

    /**
     * @return array{id:int,class_id:int,title:string,description:string,code:string}|null
     */
    public function find(int $id): ?array
    {
        $row = $this->database->connection()->table('methods')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : [
            'id' => (int)$row->id,
            'class_id' => (int)$row->class_id,
            'title' => (string)$row->title,
            'description' => (string)($row->description ?? ''),
            'code' => (string)($row->code ?? ''),
        ];
    }

    /**
     * @return list<array{id:int,class_id:int,title:string,description:string}>
     */
    public function forClass(int $classId): array
    {
        return $this->database->connection()->table('methods')
            ->where('class_id', $classId)
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'class_id' => (int)$row->class_id,
                'title' => (string)$row->title,
                'description' => (string)($row->description ?? ''),
            ])
            ->all();
    }

    /**
     * @return array{id:int,class_id:int,title:string,description:string,code:string}|null
     */
    public function findForClass(int $classId, string $title): ?array
    {
        $row = $this->database->connection()->table('methods')
            ->where('class_id', $classId)
            ->where('title', $title)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : [
            'id' => (int)$row->id,
            'class_id' => (int)$row->class_id,
            'title' => (string)$row->title,
            'description' => (string)($row->description ?? ''),
            'code' => (string)($row->code ?? ''),
        ];
    }

    public function create(int $classId, string $title, string $description, string $code): int
    {
        $now = date('Y-m-d H:i:s');

        return (int)$this->database->connection()->table('methods')->insertGetId([
            'class_id' => max(0, $classId),
            'title' => $this->title($title),
            'description' => $description,
            'code' => $code,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function update(int $id, int $classId, string $title, string $description, string $code): void
    {
        $this->database->connection()->table('methods')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update([
                'class_id' => max(0, $classId),
                'title' => $this->title($title),
                'description' => $description,
                'code' => $code,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function delete(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('methods')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    private function title(string $title): string
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 191) {
            throw new InvalidArgumentException('Название метода обязательно, до 191 символа.');
        }

        return $title;
    }
}
