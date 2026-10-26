<?php

declare(strict_types=1);

namespace MajorDoMo\Properties;

use InvalidArgumentException;
use MajorDoMo\Database\DatabaseManager;

/**
 * Свойства классов MyDoMo.
 */
final readonly class PropertyRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    /**
     * @return list<array{id:int,class_id:int,title:string,data_type:string,keep_history:bool}>
     */
    public function all(): array
    {
        return $this->database->connection()->table('properties')
            ->whereNull('deleted_at')
            ->orderBy('class_id')
            ->orderBy('title')
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'class_id' => (int)$row->class_id,
                'title' => (string)$row->title,
                'data_type' => (string)$row->data_type,
                'keep_history' => (int)$row->keep_history === 1,
            ])
            ->all();
    }

    /**
     * @return array{id:int,class_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string}|null
     */
    public function find(int $id): ?array
    {
        $row = $this->database->connection()->table('properties')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * Свойство объекта: сначала в его классе, затем глобальное (`class_id = 0`).
     *
     * Поиск по одному названию без класса не используется: в разных классах
     * могут быть одноимённые свойства, и запись `sg('Кухня.temperature')`
     * обязана попасть в свойство класса «Кухни», а не в первое совпадение.
     *
     * @return array{id:int,class_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string}|null
     */
    public function resolveForClass(int $classId, string $title): ?array
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        $query = $this->database->connection()->table('properties')
            ->where('title', $title)
            ->whereNull('deleted_at');

        $row = (clone $query)->where('class_id', max(0, $classId))->first();
        if ($row === null && $classId > 0) {
            $row = $query->where('class_id', 0)->first();
        }

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * @return list<array{id:int,title:string,data_type:string}>
     */
    public function forClass(int $classId): array
    {
        return $this->database->connection()->table('properties')
            ->where('class_id', $classId)
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'title' => (string)$row->title,
                'data_type' => (string)$row->data_type,
            ])
            ->all();
    }

    /**
     * @return array{id:int,class_id:int,title:string}|null
     */
    public function findByTitle(string $title): ?array
    {
        $row = $this->database->connection()->table('properties')
            ->where('title', $title)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : [
            'id' => (int)$row->id,
            'class_id' => (int)$row->class_id,
            'title' => (string)$row->title,
        ];
    }

    public function create(int $classId, string $title, string $description, string $dataType, bool $keepHistory, string $onChange): int
    {
        $now = date('Y-m-d H:i:s');

        return (int)$this->database->connection()->table('properties')->insertGetId([
            'class_id' => max(0, $classId),
            'title' => $this->title($title),
            'description' => $description,
            'data_type' => $dataType,
            'keep_history' => $keepHistory ? 1 : 0,
            'on_change' => $onChange,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function update(int $id, int $classId, string $title, string $description, string $dataType, bool $keepHistory, string $onChange): void
    {
        $this->database->connection()->table('properties')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update([
                'class_id' => max(0, $classId),
                'title' => $this->title($title),
                'description' => $description,
                'data_type' => $dataType,
                'keep_history' => $keepHistory ? 1 : 0,
                'on_change' => $onChange,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function delete(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('properties')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    /**
     * @return array{id:int,class_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string}
     */
    private function hydrate(object $row): array
    {
        // `??` терпит БД, где миграция полей ещё не применена: чтение значения
        // не должно падать из-за отсутствующей необязательной настройки.
        return [
            'id' => (int)$row->id,
            'class_id' => (int)$row->class_id,
            'title' => (string)$row->title,
            'description' => (string)($row->description ?? ''),
            'data_type' => (string)($row->data_type ?? 'string'),
            'keep_history' => (int)($row->keep_history ?? 0) === 1,
            'on_change' => (string)($row->on_change ?? ''),
        ];
    }

    private function title(string $title): string
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 191) {
            throw new InvalidArgumentException('Название свойства обязательно, до 191 символа.');
        }

        return $title;
    }
}
