<?php

declare(strict_types=1);

namespace MajorDoMo\Classes;

use InvalidArgumentException;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Rename\NameReferenceUpdater;
use MajorDoMo\Support\Identifier;
use Throwable;

/**
 * Классы объектов MyDoMo и их иерархия.
 *
 * 2026-10-29 — дерево строится также из готового снимка без повторного чтения БД.
 * 2026-10-27 — системный Users нельзя удалить, переименовать или перенести.
 * 2026-10-27 — добавлены parent_id, подклассы и безопасная проверка циклов:
 * класс наследует свойства/методы всех предков, а объекты подкласса остаются
 * объектами родительского класса для выборок и наследования.
 * 2026-10-27 — системное имя проверяется латиницей, у класса появилось
 * описание для карточки и дерева.
 */
final readonly class ClassRepository
{
    private const MAX_TITLE_LENGTH = 191;

    public function __construct(
        private DatabaseManager $database,
        private ?NameReferenceUpdater $references = null
    ) {
    }

    /**
     * @return list<array{id:int,title:string,parent_id:int,description:string}>
     */
    public function all(): array
    {
        return $this->database->connection()->table('classes')
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get()
            ->map(fn (object $row): array => $this->hydrate($row))
            ->all();
    }

    /**
     * Названия классов для живой проверки имени: занятые, кроме текущего.
     *
     * @return list<string>
     */
    public function titles(int $exceptId = 0): array
    {
        $query = $this->database->connection()->table('classes')
            ->whereNull('deleted_at')
            ->orderBy('title');
        if ($exceptId > 0) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->pluck('title')->map(static fn (mixed $title): string => (string)$title)->all();
    }

    /**
     * @return array{id:int,title:string,parent_id:int,description:string}|null
     */
    public function find(int $id): ?array
    {
        $row = $this->database->connection()->table('classes')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * Плоский список дерева классов для таблицы админки.
     *
     * При поиске возвращаются только совпавшие классы без раскрытия: иначе
     * дерево пришлось бы строить от скрытых родителей и показывать лишнее.
     *
     * @return list<array{
     *     id:int,title:string,parent_id:int,description:string,depth:int,ancestors:list<int>,
     *     path:string,has_children:bool,children_count:int
     * }>
     */
    public function flattened(string $search = ''): array
    {
        return $this->flattenedFrom($this->all(), $search);
    }

    /**
     * @param list<array{id:int,title:string,parent_id:int,description:string}> $classes
     * @return list<array{id:int,title:string,parent_id:int,description:string,depth:int,ancestors:list<int>,path:string,has_children:bool,children_count:int}>
     */
    public function flattenedFrom(array $classes, string $search = ''): array
    {
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

    public function create(string $title, int $parentId = 0, string $description = ''): int
    {
        $title = Identifier::assert($title, 'класса');
        $parentId = max(0, $parentId);
        $this->assertParent($parentId);
        $this->assertTitleFree($title);

        $now = date('Y-m-d H:i:s');

        return (int)$this->database->connection()->table('classes')->insertGetId([
            'title' => $title,
            'parent_id' => $parentId,
            'description' => trim($description),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function update(int $id, string $title, int $parentId = 0, string $description = ''): void
    {
        $title = Identifier::assert($title, 'класса');
        $parentId = max(0, $parentId);
        $this->assertParent($parentId, $id);
        $this->assertTitleFree($title, $id);

        $current = $this->find($id);
        if ($current !== null && $current['title'] === 'Users'
            && ($title !== 'Users' || $parentId !== $current['parent_id'])) {
            throw new InvalidArgumentException('Класс Users используется учётными записями: его имя и родителя менять нельзя.');
        }
        if ($current !== null && (int)$current['parent_id'] !== $parentId) {
            $this->assertMembersUniqueForParent($id, $parentId);
        }

        $connection = $this->database->connection();
        try {
            $connection->transaction(function () use ($connection, $id, $title, $parentId, $description, $current): void {
                $connection->table('classes')
                    ->where('id', $id)
                    ->whereNull('deleted_at')
                    ->update([
                        'title' => $title,
                        'parent_id' => $parentId,
                        'description' => trim($description),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                if ($current !== null && $current['title'] !== $title) {
                    $this->references?->classRenamed($current['title'], $title);
                }
            });
        } catch (Throwable $error) {
            $this->references?->discardPendingFiles();
            throw $error;
        }
        $this->references?->flushModuleFiles();
    }

    public function delete(int $id): void
    {
        $class = $this->find($id);
        if ($class === null) {
            return;
        }
        if ($class['title'] === 'Users') {
            throw new InvalidArgumentException('Системный класс Users удалить нельзя.');
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
            ->update([
                'title' => $this->archivedTitle((string)$class['title'], $id),
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
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

    /**
     * Перенос под дерево не должен создавать одноимённые свойства/методы.
     *
     * Проверяем всех членов переносимого поддерева (включая объектные) против
     * классовых членов новых предков: иначе после смены родителя появился бы
     * дубль, который запрещён при создании.
     */
    private function assertMembersUniqueForParent(int $classId, int $parentId): void
    {
        if ($parentId <= 0) {
            return;
        }
        $ancestorIds = $this->ancestorIds($parentId);
        $subtreeIds = $this->descendantIds($classId);
        if ($ancestorIds === [] || $subtreeIds === []) {
            return;
        }
        $schema = $this->database->connection()->getSchemaBuilder();
        foreach (['properties' => 'Свойство', 'methods' => 'Метод'] as $table => $label) {
            if (!$schema->hasTable($table) || !$schema->hasColumn($table, 'title')
                || !$schema->hasColumn($table, 'object_id')) {
                continue;
            }
            $taken = $this->database->connection()->table($table)
                ->whereIn('class_id', $ancestorIds)
                ->where('object_id', 0)
                ->whereNull('deleted_at')
                ->pluck('title')
                ->map(static fn (mixed $value): string => mb_strtolower((string)$value))
                ->all();
            if ($taken === []) {
                continue;
            }
            $rows = $this->database->connection()->table($table)
                ->whereIn('class_id', $subtreeIds)
                ->whereNull('deleted_at')
                ->pluck('title');
            foreach ($rows as $title) {
                if (in_array(mb_strtolower((string)$title), $taken, true)) {
                    throw new InvalidArgumentException(
                        $label . ' «' . (string)$title . '» уже есть у нового родительского класса. '
                        . 'Переименуйте его перед переносом.'
                    );
                }
            }
        }
    }

    private function assertTitleFree(string $title, int $exceptId = 0): void
    {
        $query = $this->database->connection()->table('classes')
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
            ->whereNull('deleted_at');
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

    /**
     * Освобождает системное имя: уникальный индекс БД не должен блокировать
     * повторное создание класса с тем же именем после удаления.
     */
    private function archivedTitle(string $title, int $id): string
    {
        $suffix = '~deleted~' . $id;

        return mb_substr($title, 0, self::MAX_TITLE_LENGTH - mb_strlen($suffix)) . $suffix;
    }

    /**
     * @return array{id:int,title:string,parent_id:int,description:string}
     */
    private function hydrate(object $row): array
    {
        // `??` терпит БД, где миграция описания ещё не применена.
        return [
            'id' => (int)$row->id,
            'title' => (string)$row->title,
            'parent_id' => (int)($row->parent_id ?? 0),
            'description' => trim((string)($row->description ?? '')),
        ];
    }
}
