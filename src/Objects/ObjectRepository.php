<?php

declare(strict_types=1);

namespace MajorDoMo\Objects;

use InvalidArgumentException;
use MajorDoMo\Database\DatabaseManager;

/**
 * Объекты MyDoMo.
 */
final readonly class ObjectRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    /**
     * Объекты с поиском и фильтрами по классу и локации.
     *
     * @return list<array{id:int,title:string,class_id:int,location_id:int,description:string,updated_at:string}>
     */
    public function all(string $search = '', int $classId = 0, int $locationId = 0): array
    {
        $query = $this->database->connection()->table('objects')->whereNull('deleted_at');
        if ($classId > 0) {
            $query->where('class_id', $classId);
        }
        if ($locationId > 0) {
            $query->where('location_id', $locationId);
        }
        if ($search !== '') {
            $query->where(static function ($query) use ($search): void {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        return $query->orderBy('title')
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'title' => (string)$row->title,
                'class_id' => (int)$row->class_id,
                'location_id' => (int)$row->location_id,
                'description' => (string)($row->description ?? ''),
                'updated_at' => (string)($row->updated_at ?? ''),
            ])
            ->all();
    }

    /**
     * Количество объектов по классам: один запрос на страницу классов.
     *
     * @return array<int,int>
     */
    public function countsByClass(): array
    {
        $counts = [];
        $rows = $this->database->connection()->table('objects')
            ->whereNull('deleted_at')
            ->select('class_id')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('class_id')
            ->get();
        foreach ($rows as $row) {
            $counts[(int)$row->class_id] = (int)$row->aggregate;
        }

        return $counts;
    }

    /**
     * Объекты, разложенные по классам, для дерева классов.
     *
     * @return array<int,list<array{id:int,title:string}>>
     */
    public function groupedByClass(): array
    {
        $grouped = [];
        $rows = $this->database->connection()->table('objects')
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get(['id', 'title', 'class_id']);
        foreach ($rows as $row) {
            $grouped[(int)$row->class_id][] = [
                'id' => (int)$row->id,
                'title' => (string)$row->title,
            ];
        }

        return $grouped;
    }

    /**
     * @return array{id:int,title:string,class_id:int,location_id:int,description:string}|null
     */
    public function find(int $id): ?array
    {
        $row = $this->findRow($id);

        return $row === null ? null : [
            'id' => (int)$row->id,
            'title' => (string)$row->title,
            'class_id' => (int)$row->class_id,
            'location_id' => (int)$row->location_id,
            'description' => (string)($row->description ?? ''),
        ];
    }

    /**
     * @return array{id:int,title:string,class_id:int,location_id:int,description:string}|null
     */
    public function findByTitle(string $title): ?array
    {
        $row = $this->database->connection()->table('objects')
            ->where('title', $title)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : [
            'id' => (int)$row->id,
            'title' => (string)$row->title,
            'class_id' => (int)$row->class_id,
            'location_id' => (int)$row->location_id,
            'description' => (string)($row->description ?? ''),
        ];
    }

    public function create(string $title, int $classId, int $locationId, string $description): int
    {
        $now = date('Y-m-d H:i:s');

        return (int)$this->database->connection()->table('objects')->insertGetId([
            'title' => $this->title($title),
            'class_id' => max(0, $classId),
            'location_id' => max(0, $locationId),
            'description' => $description,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function update(int $id, string $title, int $classId, int $locationId, string $description): void
    {
        $this->database->connection()->table('objects')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update([
                'title' => $this->title($title),
                'class_id' => max(0, $classId),
                'location_id' => max(0, $locationId),
                'description' => $description,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function delete(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('objects')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    private function findRow(int $id): ?object
    {
        return $this->database->connection()->table('objects')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();
    }

    private function title(string $title): string
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 191) {
            throw new InvalidArgumentException('Название объекта обязательно, до 191 символа.');
        }

        return $title;
    }
}
