<?php

declare(strict_types=1);

namespace MajorDoMo\Classes;

use InvalidArgumentException;
use MajorDoMo\Database\DatabaseManager;

/**
 * Классы объектов MyDoMo и их иерархия.
 *
 * 2026-10-27 — добавлены parent_id, подклассы и безопасная проверка циклов:
 * класс наследует свойства/методы всех предков, а объекты подкласса остаются
 * объектами родительского класса для выборок и наследования.
 */
final readonly class ClassRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    /**
     * @return list<array{id:int,title:string,parent_id:int}>
     */
    public function all(): array
    {
        return $this->database->connection()->table('classes')
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'title' => (string)$row->title,
                'parent_id' => (int)($row->parent_id ?? 0),
            ])
            ->all();
    }

    /**
     * @return array{id:int,title:string,parent_id:int}|null
     */
    public function find(int $id): ?array
    {
        $row = $this->database->connection()->table('classes')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : [
            'id' => (int)$row->id,
            'title' => (string)$row->title,
            'parent_id' => (int)($row->parent_id ?? 0),
        ];
    }

    /**
     * Плоский список дерева классов для таблицы админки.
     *
     * При поиске возвращаются только совпавшие классы без раскрытия: иначе
     * дерево пришлось бы строить от скрытых родителей и показывать лишнее.
     *
     * @return list<array{
     *     id:int,title:string,parent_id:int,depth:int,ancestors:list<int>,
     *     path:string,has_children:bool,children_count:int
     * }>
     */
    public function flattened(string $search = ''): array
    {
        $classes = $this->all();
        $byId = [];
        foreach ($classes as $class) {
            $byId[$class['id']] = $class;
        }

        $search = trim($search);
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = [];
            foreach ($classes as $class) {
                if (!str_contains(mb_strtolower($class['title']), $needle)) {
                    continue;
                }
                $rows[] = $class + [
                    'depth' => 0,
                    'ancestors' => [],
                    'path' => $this->pathFrom($byId, $class['id']),
                    'has_children' => false,
                    'children_count' => 0,
                ];
            }

            return $rows;
        }

        $children = $this->childrenMap($classes);
        $roots = [];
        foreach ($classes as $class) {
            $parentId = (int)$class['parent_id'];
            if ($parentId <= 0 || !isset($byId[$parentId])) {
                $roots[] = $class['id'];
            }
        }

        $rows = [];
        $visited = [];
        $append = function (int $id, int $depth, array $ancestors) use (&$append, &$rows, &$visited, $byId, $children): void {
            if (isset($visited[$id]) || !isset($byId[$id])) {
                return;
            }
            $visited[$id] = true;
            $childIds = $children[$id] ?? [];
            $rows[] = $byId[$id] + [
                'depth' => $depth,
                'ancestors' => $ancestors,
                'path' => $this->pathFrom($byId, $id),
                'has_children' => $childIds !== [],
                'children_count' => count($childIds),
            ];
            foreach ($childIds as $childId) {
                $append($childId, $depth + 1, [...$ancestors, $id]);
            }
        };

        foreach ($roots as $rootId) {
            $append($rootId, 0, []);
        }
        // Битый/циклический parent_id не должен скрывать классы из админки.
        foreach ($classes as $class) {
            if (!isset($visited[$class['id']])) {
                $append($class['id'], 0, []);
            }
        }

        return $rows;
    }

    /**
     * Цепочка от класса к корню; порядок — ближайший родитель первым.
     *
     * @return list<array{id:int,title:string,parent_id:int}>
     */
    public function lineage(int $classId): array
    {
        $byId = [];
        foreach ($this->all() as $class) {
            $byId[$class['id']] = $class;
        }

        return $this->lineageFrom($byId, $classId);
    }

    /**
     * @param array<int,array{id:int,title:string,parent_id:int}> $byId
     * @return list<array{id:int,title:string,parent_id:int}>
     */
    public function lineageFrom(array $byId, int $classId): array
    {
        $lineage = [];
        $seen = [];
        $current = $classId;
        while ($current > 0 && isset($byId[$current]) && !isset($seen[$current])) {
            $seen[$current] = true;
            $lineage[] = $byId[$current];
            $current = (int)$byId[$current]['parent_id'];
        }

        return $lineage;
    }

    /**
     * @return list<array{id:int,title:string,parent_id:int}>
     */
    public function children(int $classId): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (array $class): bool => (int)$class['parent_id'] === $classId
        ));
    }

    /**
     * Класс, его подклассы и подклассы подклассов. Используется для объектов
     * класса: объект подкласса остаётся экземпляром родительского класса.
     *
     * @return list<int>
     */
    public function descendantIds(int $classId, bool $includeSelf = true): array
    {
        $children = $this->childrenMap($this->all());
        $ids = [];
        $queue = [$classId];
        $seen = [];
        while ($queue !== []) {
            $current = (int)array_shift($queue);
            if ($current <= 0 || isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            if ($includeSelf || $current !== $classId) {
                $ids[] = $current;
            }
            foreach ($children[$current] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    public function ancestorIds(int $classId, bool $includeSelf = true): array
    {
        $ids = [];
        foreach ($this->lineage($classId) as $index => $class) {
            if ($index === 0 && !$includeSelf) {
                continue;
            }
            $ids[] = (int)$class['id'];
        }

        return $ids;
    }

    public function create(string $title, int $parentId = 0): int
    {
        $title = $this->title($title);
        $parentId = max(0, $parentId);
        $this->assertParent($parentId);
        $this->assertTitleFree($title);

        $now = date('Y-m-d H:i:s');

        return (int)$this->database->connection()->table('classes')->insertGetId([
            'title' => $title,
            'parent_id' => $parentId,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function update(int $id, string $title, int $parentId = 0): void
    {
        $title = $this->title($title);
        $parentId = max(0, $parentId);
        $this->assertParent($parentId, $id);
        $this->assertTitleFree($title, $id);

        $this->database->connection()->table('classes')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update([
                'title' => $title,
                'parent_id' => $parentId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function delete(int $id): void
    {
        $class = $this->find($id);
        if ($class === null) {
            return;
        }
        if ($this->children($id) !== []) {
            throw new InvalidArgumentException('Сначала перенесите или удалите подклассы этого класса.');
        }
        if ($this->hasObjects($id)) {
            throw new InvalidArgumentException('Сначала перенесите или удалите объекты этого класса.');
        }

        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('classes')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    /**
     * @param list<array{id:int,title:string,parent_id:int}> $classes
     * @return array<int,list<int>>
     */
    private function childrenMap(array $classes): array
    {
        $children = [];
        foreach ($classes as $class) {
            $parentId = (int)$class['parent_id'];
            if ($parentId > 0) {
                $children[$parentId][] = (int)$class['id'];
            }
        }

        return $children;
    }

    /**
     * @param array<int,array{id:int,title:string,parent_id:int}> $byId
     */
    private function pathFrom(array $byId, int $classId): string
    {
        $titles = [];
        foreach (array_reverse($this->lineageFrom($byId, $classId)) as $class) {
            $titles[] = (string)$class['title'];
        }

        return implode(' → ', $titles);
    }

    private function assertParent(int $parentId, int $classId = 0): void
    {
        if ($parentId <= 0) {
            return;
        }
        if ($this->find($parentId) === null) {
            throw new InvalidArgumentException('Родительский класс не найден.');
        }
        if ($classId > 0 && ($parentId === $classId || in_array($parentId, $this->descendantIds($classId), true))) {
            throw new InvalidArgumentException('Класс нельзя сделать подклассом самого себя или своего подкласса.');
        }
    }

    private function assertTitleFree(string $title, int $exceptId = 0): void
    {
        $query = $this->database->connection()->table('classes')->where('title', $title);
        if ($exceptId > 0) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->exists()) {
            throw new InvalidArgumentException('Класс с таким названием уже существует.');
        }
    }

    private function hasObjects(int $classId): bool
    {
        $schema = $this->database->connection()->getSchemaBuilder();
        if (!$schema->hasTable('objects')) {
            return false;
        }

        return $this->database->connection()->table('objects')
            ->where('class_id', $classId)
            ->whereNull('deleted_at')
            ->exists();
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
