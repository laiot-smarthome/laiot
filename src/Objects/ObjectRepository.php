<?php

declare(strict_types=1);

namespace MajorDoMo\Objects;

use InvalidArgumentException;
use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Properties\PropertySubscriptionRepository;
use MajorDoMo\Rename\NameReferenceUpdater;
use MajorDoMo\Support\Identifier;
use Throwable;

/**
 * Объекты MyDoMo.
 *
 * 2026-10-27 — имя, класс и удаление связанного пользователя управляются
 * через учётную запись, чтобы связь по id не расходилась с логином.
 * 2026-10-27 — удаление объекта снимает подписки модулей на его свойства.
 * 2026-10-27 — выборка по классу учитывает подклассы: объект подкласса
 * остаётся экземпляром родительского класса, как в alpha.
 * 2026-10-27 — имя объекта латиницей и уникально глобально: так
 * setGlobal('Object.Property') указывает ровно на один объект. Локация —
 * свойство объекта, у класса её нет.
 */
final readonly class ObjectRepository
{
    private const MAX_TITLE_LENGTH = 191;

    public function __construct(
        private DatabaseManager $database,
        private ClassRepository $classes,
        private ?PropertySubscriptionRepository $subscriptions = null,
        private ?NameReferenceUpdater $references = null
    ) {
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
     * Объекты класса; с `$withDescendants` — вместе с объектами подклассов.
     *
     * @return list<array{id:int,title:string,class_id:int,class_title:string,location_id:int,location_title:string,description:string,updated_at:string}>
     */
    public function forClass(int $classId, bool $withDescendants = false): array
    {
        if ($classId <= 0) {
            return [];
        }
        $classIds = $withDescendants ? $this->classes->descendantIds($classId) : [$classId];
        if ($classIds === []) {
            return [];
        }

        $rows = $this->database->connection()->table('objects as o')
            ->leftJoin('classes as c', 'c.id', '=', 'o.class_id')
            ->leftJoin('locations as l', 'l.id', '=', 'o.location_id')
            ->whereIn('o.class_id', $classIds)
            ->whereNull('o.deleted_at')
            ->orderBy('c.title')
            ->orderBy('o.title')
            ->get([
                'o.id',
                'o.title',
                'o.class_id',
                'o.location_id',
                'o.description',
                'o.updated_at',
                'c.title as class_title',
                'l.title as location_title',
            ]);

        return $rows->map(static fn (object $row): array => [
            'id' => (int)$row->id,
            'title' => (string)$row->title,
            'class_id' => (int)$row->class_id,
            'class_title' => (string)($row->class_title ?? ''),
            'location_id' => (int)$row->location_id,
            'location_title' => (string)($row->location_title ?? ''),
            'description' => (string)($row->description ?? ''),
            'updated_at' => (string)($row->updated_at ?? ''),
        ])->all();
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
     * Локация берётся у объекта: класс не привязан к помещению.
     *
     * @return array<int,list<array{id:int,title:string,location_id:int,location_title:string,description:string}>>
     */
    public function groupedByClass(): array
    {
        $grouped = [];
        $rows = $this->database->connection()->table('objects as o')
            ->leftJoin('locations as l', 'l.id', '=', 'o.location_id')
            ->whereNull('o.deleted_at')
            ->orderBy('o.title')
            ->get([
                'o.id',
                'o.title',
                'o.class_id',
                'o.location_id',
                'o.description',
                'l.title as location_title',
            ]);
        foreach ($rows as $row) {
            $grouped[(int)$row->class_id][] = [
                'id' => (int)$row->id,
                'title' => (string)$row->title,
                'location_id' => (int)$row->location_id,
                'location_title' => (string)($row->location_title ?? ''),
                'description' => trim((string)($row->description ?? '')),
            ];
        }

        return $grouped;
    }

    /**
     * Названия объектов для живой проверки имени: занятые, кроме текущего.
     *
     * @return list<string>
     */
    public function titles(int $exceptId = 0): array
    {
        $query = $this->database->connection()->table('objects')
            ->whereNull('deleted_at')
            ->orderBy('title');
        if ($exceptId > 0) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->pluck('title')->map(static fn (mixed $title): string => (string)$title)->all();
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
        $this->assertClass($classId);
        $title = Identifier::assert($title, 'объекта');
        $this->assertTitleFree($title);
        $locationId = $this->assertLocation($locationId);
        $now = date('Y-m-d H:i:s');

        return (int)$this->database->connection()->table('objects')->insertGetId([
            'title' => $title,
            'class_id' => $classId,
            'location_id' => $locationId,
            'description' => trim($description),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function update(int $id, string $title, int $classId, int $locationId, string $description): void
    {
        $this->assertClass($classId);
        $title = Identifier::assert($title, 'объекта');
        $this->assertTitleFree($title, $id);
        $locationId = $this->assertLocation($locationId);

        $current = $this->find($id);
        $user = $this->linkedUser($id);
        if ($user !== null && ($title !== $user->username || $classId !== (int)($current['class_id'] ?? 0))) {
            throw new InvalidArgumentException('Имя и класс объекта пользователя меняются через раздел «Пользователи».');
        }
        if ($current !== null && (int)$current['class_id'] !== $classId) {
            $this->assertMembersUniqueForClass($id, $classId);
        }

        $connection = $this->database->connection();
        try {
            $connection->transaction(function () use ($connection, $id, $title, $classId, $locationId, $description, $current): void {
                $connection->table('objects')
                    ->where('id', $id)
                    ->whereNull('deleted_at')
                    ->update([
                        'title' => $title,
                        'class_id' => $classId,
                        'location_id' => $locationId,
                        'description' => trim($description),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                if ($current !== null && $current['title'] !== $title) {
                    $this->references?->objectRenamed($id, $current['title'], $title);
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
        if ($this->linkedUser($id) !== null) {
            throw new InvalidArgumentException('Удалите пользователя в разделе «Пользователи» — его объект будет удалён вместе с ним.');
        }
        $object = $this->find($id);
        if ($object === null) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('objects')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update([
                'title' => $this->archivedTitle((string)$object['title'], $id),
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
        // Подписки на свойства удалённого объекта не должны оставаться
        // в таблице доставки.
        $this->subscriptions?->deleteForObject($id);
    }

    private function assertClass(int $classId): void
    {
        if ($classId <= 0) {
            throw new InvalidArgumentException('Выберите класс объекта: объект всегда экземпляр класса.');
        }
        if ($this->classes->find($classId) === null) {
            throw new InvalidArgumentException('Класс объекта не найден.');
        }
    }

    private function linkedUser(int $objectId): ?object
    {
        $schema = $this->database->connection()->getSchemaBuilder();
        if (!$schema->hasTable('users') || !$schema->hasColumn('users', 'object_id')) {
            return null;
        }

        return $this->database->connection()->table('users')->where('object_id', $objectId)
            ->whereNull('deleted_at')->first();
    }

    /**
     * Уникальность глобальная: getGlobal('Object.Property') не должен
     * зависеть от того, из какого класса взят объект.
     */
    private function assertTitleFree(string $title, int $exceptId = 0): void
    {
        $query = $this->database->connection()->table('objects')
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
            ->whereNull('deleted_at');
        if ($exceptId > 0) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->exists()) {
            throw new InvalidArgumentException('Объект с таким названием уже существует.');
        }
    }

    /**
     * Смена класса объекта не должна давать дубли с членами нового класса.
     */
    private function assertMembersUniqueForClass(int $objectId, int $classId): void
    {
        $ancestorIds = $this->classes->ancestorIds($classId);
        if ($ancestorIds === []) {
            return;
        }
        $schema = $this->database->connection()->getSchemaBuilder();
        foreach (['properties' => 'Свойство', 'methods' => 'Метод'] as $table => $label) {
            if (!$schema->hasTable($table) || !$schema->hasColumn($table, 'title')
                || !$schema->hasColumn($table, 'object_id')) {
                continue;
            }
            $classTitles = $this->database->connection()->table($table)
                ->whereIn('class_id', $ancestorIds)
                ->where('object_id', 0)
                ->whereNull('deleted_at')
                ->pluck('title')
                ->map(static fn (mixed $value): string => mb_strtolower((string)$value))
                ->all();
            if ($classTitles === []) {
                continue;
            }
            $objectTitles = $this->database->connection()->table($table)
                ->where('object_id', $objectId)
                ->whereNull('deleted_at')
                ->pluck('title');
            foreach ($objectTitles as $title) {
                if (in_array(mb_strtolower((string)$title), $classTitles, true)) {
                    throw new InvalidArgumentException(
                        $label . ' «' . (string)$title . '» уже есть у нового класса объекта. '
                        . 'Переименуйте или удалите его перед переносом.'
                    );
                }
            }
        }
    }

    private function assertLocation(int $locationId): int
    {
        $locationId = max(0, $locationId);
        if ($locationId === 0) {
            return 0;
        }
        $exists = $this->database->connection()->table('locations')
            ->where('id', $locationId)
            ->whereNull('deleted_at')
            ->exists();
        if (!$exists) {
            throw new InvalidArgumentException('Расположение объекта не найдено.');
        }

        return $locationId;
    }

    private function findRow(int $id): ?object
    {
        return $this->database->connection()->table('objects')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Освобождает имя объекта для повторного создания: архив остаётся
     * в БД, но его title больше не занимает уникальный индекс.
     */
    private function archivedTitle(string $title, int $id): string
    {
        $suffix = '~deleted~' . $id;

        return mb_substr($title, 0, self::MAX_TITLE_LENGTH - mb_strlen($suffix)) . $suffix;
    }
}
