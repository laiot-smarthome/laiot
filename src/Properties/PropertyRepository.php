<?php

declare(strict_types=1);

namespace MajorDoMo\Properties;

use InvalidArgumentException;
use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Database\DatabaseManager;

/**
 * Свойства классов и объектов MyDoMo.
 *
 * 2026-10-27 — свойства ищутся по цепочке наследования классов; добавлены
 * объектные свойства (`object_id`), которые переопределяют классовое свойство
 * с тем же названием только у одного объекта.
 */
final readonly class PropertyRepository
{
    public function __construct(
        private DatabaseManager $database,
        private ClassRepository $classes
    ) {
    }

    /**
     * @return list<array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string}>
     */
    public function all(string $search = '', int $classId = 0): array
    {
        $query = $this->database->connection()->table('properties')->whereNull('deleted_at');
        if ($classId > 0) {
            $query->where('class_id', $classId);
        }
        if ($search !== '') {
            $query->where(static function ($query) use ($search): void {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('on_change', 'like', '%' . $search . '%');
            });
        }

        return $query->orderBy('class_id')
            ->orderBy('object_id')
            ->orderBy('title')
            ->get()
            ->map(fn (object $row): array => $this->hydrate($row))
            ->all();
    }

    /**
     * Собственные свойства класса без унаследованных.
     *
     * @return list<array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string,origin:string,owner_class_id:int,owner_class_title:string}>
     */
    public function ownForClass(int $classId): array
    {
        $class = $this->classes->find($classId);
        if ($class === null) {
            return [];
        }

        return $this->rowsForClasses([$classId], $classId, (string)$class['title']);
    }

    /**
     * Действующие свойства класса: свои и унаследованные от предков.
     *
     * Ближайший класс в цепочке побеждает при совпадении названия — так
     * подкласс может переопределить свойство родителя.
     *
     * @return list<array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string,origin:string,owner_class_id:int,owner_class_title:string}>
     */
    public function effectiveForClass(int $classId): array
    {
        $classes = $this->classes->all();
        if ($classes === []) {
            return [];
        }
        $byId = [];
        foreach ($classes as $class) {
            $byId[$class['id']] = $class;
        }
        $lineage = $this->classes->lineageFrom($byId, $classId);
        if ($lineage === []) {
            return [];
        }

        $ids = [];
        foreach ($lineage as $class) {
            $ids[] = (int)$class['id'];
        }
        $rows = $this->database->connection()->table('properties')
            ->whereIn('class_id', $ids)
            ->where('object_id', 0)
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get();

        $byClass = [];
        foreach ($rows as $row) {
            $byClass[(int)$row->class_id][] = $row;
        }

        $known = [];
        $result = [];
        foreach ($lineage as $owner) {
            $ownerId = (int)$owner['id'];
            foreach ($byClass[$ownerId] ?? [] as $row) {
                $title = (string)$row->title;
                if (isset($known[$title])) {
                    continue;
                }
                $known[$title] = true;
                $result[] = $this->hydrate($row) + [
                    'origin' => $ownerId === $classId ? 'own' : 'inherited',
                    'owner_class_id' => $ownerId,
                    'owner_class_title' => (string)$owner['title'],
                ];
            }
        }

        usort($result, static fn (array $a, array $b): int => strnatcasecmp($a['title'], $b['title']));

        return $result;
    }

    /**
     * Действующие свойства конкретного объекта.
     *
     * Сначала объектное свойство, затем ближайший класс и его предки. Значение
     * хранится у объекта; на уровне класса значения нет.
     *
     * @return list<array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string,origin:string,owner_class_id:int,owner_class_title:string}>
     */
    public function forObject(int $objectId): array
    {
        $classId = $this->objectClassId($objectId);
        if ($classId === null) {
            return [];
        }

        $known = [];
        $result = [];
        $objectProperties = $this->database->connection()->table('properties')
            ->where('object_id', $objectId)
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get();
        foreach ($objectProperties as $row) {
            $title = (string)$row->title;
            $known[$title] = true;
            $result[] = $this->hydrate($row) + [
                'origin' => 'object',
                'owner_class_id' => $classId,
                'owner_class_title' => $this->className($classId),
            ];
        }

        foreach ($this->effectiveForClass($classId) as $property) {
            if (isset($known[$property['title']])) {
                continue;
            }
            $known[$property['title']] = true;
            $result[] = $property;
        }

        usort($result, static fn (array $a, array $b): int => strnatcasecmp($a['title'], $b['title']));

        return $result;
    }

    /**
     * Количество собственных свойств по классам.
     *
     * @return array<int,int>
     */
    public function countsByClass(): array
    {
        $counts = [];
        $rows = $this->database->connection()->table('properties')
            ->where('object_id', 0)
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
     * Счётчики свойств с учётом наследования для страницы классов.
     *
     * @param list<array{id:int,title:string,parent_id:int}> $classes
     * @return array<int,array{own:int,inherited:int,effective:int}>
     */
    public function effectiveCounts(array $classes): array
    {
        $byClass = [];
        foreach ($this->database->connection()->table('properties')
            ->where('object_id', 0)
            ->whereNull('deleted_at')
            ->get(['class_id', 'title']) as $row) {
            $byClass[(int)$row->class_id][] = (string)$row->title;
        }

        $byId = [];
        foreach ($classes as $class) {
            $byId[$class['id']] = $class;
        }

        $counts = [];
        foreach ($classes as $class) {
            $known = [];
            $effective = 0;
            foreach ($this->classes->lineageFrom($byId, (int)$class['id']) as $owner) {
                foreach ($byClass[(int)$owner['id']] ?? [] as $title) {
                    if (isset($known[$title])) {
                        continue;
                    }
                    $known[$title] = true;
                    $effective++;
                }
            }
            $own = count($byClass[(int)$class['id']] ?? []);
            $counts[(int)$class['id']] = [
                'own' => $own,
                'inherited' => max(0, $effective - $own),
                'effective' => $effective,
            ];
        }

        return $counts;
    }

    /**
     * @return array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string}|null
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
     * Свойство объекта: объектное свойство, затем ближайший класс и предки.
     *
     * @return array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string}|null
     */
    public function resolveForObject(int $objectId, string $title): ?array
    {
        $title = trim($title);
        if ($title === '' || $objectId <= 0) {
            return null;
        }

        $row = $this->database->connection()->table('properties')
            ->where('object_id', $objectId)
            ->where('title', $title)
            ->whereNull('deleted_at')
            ->first();
        if ($row !== null) {
            return $this->hydrate($row);
        }

        $classId = $this->objectClassId($objectId);

        return $classId === null ? null : $this->resolveForClass($classId, $title);
    }

    /**
     * Свойство класса с учётом наследования, без объектных переопределений.
     *
     * @return array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string}|null
     */
    public function resolveForClass(int $classId, string $title): ?array
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        foreach ($this->classes->lineage($classId) as $class) {
            $row = $this->database->connection()->table('properties')
                ->where('class_id', (int)$class['id'])
                ->where('object_id', 0)
                ->where('title', $title)
                ->whereNull('deleted_at')
                ->first();
            if ($row !== null) {
                return $this->hydrate($row);
            }
        }

        return null;
    }

    /**
     * Проверяет, что свойство действительно действует у объекта: объектное
     * свойство принадлежит ему, а классовое не перекрыто ближайшим свойством.
     *
     * @param array{id:int,class_id:int,object_id?:int,title:string} $property
     * @param array{id:int,class_id:int,title:string} $object
     */
    public function isApplicableToObject(array $property, array $object): bool
    {
        $objectId = (int)$object['id'];
        if ((int)($property['object_id'] ?? 0) > 0) {
            return (int)$property['object_id'] === $objectId;
        }

        $resolved = $this->resolveForObject($objectId, (string)$property['title']);

        return $resolved !== null && (int)$resolved['id'] === (int)$property['id'];
    }

    public function create(
        int $classId,
        string $title,
        string $description,
        string $dataType,
        bool $keepHistory,
        string $onChange,
        int $objectId = 0
    ): int {
        [$classId, $objectId] = $this->scope($classId, $objectId);
        $title = $this->title($title);
        $this->assertTitleFree($classId, $objectId, $title);
        $now = date('Y-m-d H:i:s');

        return (int)$this->database->connection()->table('properties')->insertGetId([
            'class_id' => $classId,
            'object_id' => $objectId,
            'title' => $title,
            'description' => $description,
            'data_type' => $this->dataType($dataType),
            'keep_history' => $keepHistory ? 1 : 0,
            'on_change' => $onChange,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function update(
        int $id,
        int $classId,
        string $title,
        string $description,
        string $dataType,
        bool $keepHistory,
        string $onChange,
        int $objectId = 0
    ): void {
        [$classId, $objectId] = $this->scope($classId, $objectId);
        $title = $this->title($title);
        $this->assertTitleFree($classId, $objectId, $title, $id);

        $this->database->connection()->table('properties')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update([
                'class_id' => $classId,
                'object_id' => $objectId,
                'title' => $title,
                'description' => $description,
                'data_type' => $this->dataType($dataType),
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
     * @param list<int> $classIds
     * @return list<array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string,origin:string,owner_class_id:int,owner_class_title:string}>
     */
    private function rowsForClasses(array $classIds, int $targetClassId, string $targetTitle): array
    {
        return $this->database->connection()->table('properties')
            ->whereIn('class_id', $classIds)
            ->where('object_id', 0)
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get()
            ->map(fn (object $row): array => $this->hydrate($row) + [
                'origin' => $targetClassId === (int)$row->class_id ? 'own' : 'inherited',
                'owner_class_id' => $targetClassId,
                'owner_class_title' => $targetTitle,
            ])
            ->all();
    }

    private function objectClassId(int $objectId): ?int
    {
        $row = $this->database->connection()->table('objects')
            ->where('id', $objectId)
            ->whereNull('deleted_at')
            ->first(['class_id']);

        return $row === null ? null : (int)$row->class_id;
    }

    private function className(int $classId): string
    {
        return (string)($this->classes->find($classId)['title'] ?? '');
    }

    /**
     * @return array{0:int,1:int} [class_id, object_id]
     */
    private function scope(int $classId, int $objectId): array
    {
        $objectId = max(0, $objectId);
        if ($objectId > 0) {
            $object = $this->database->connection()->table('objects')
                ->where('id', $objectId)
                ->whereNull('deleted_at')
                ->first(['class_id']);
            if ($object === null) {
                throw new InvalidArgumentException('Объект для свойства не найден.');
            }
            $objectClassId = (int)$object->class_id;
            if ($classId > 0 && $classId !== $objectClassId) {
                throw new InvalidArgumentException('Класс не совпадает с классом объекта.');
            }
            $classId = $objectClassId;
        }

        if ($classId > 0 && $this->classes->find($classId) === null) {
            throw new InvalidArgumentException('Класс свойства не найден.');
        }
        if ($classId <= 0 && $objectId <= 0) {
            throw new InvalidArgumentException('Свойство должно принадлежать классу или объекту.');
        }

        return [$classId, $objectId];
    }

    private function assertTitleFree(int $classId, int $objectId, string $title, int $exceptId = 0): void
    {
        $query = $this->database->connection()->table('properties')
            ->where('class_id', $classId)
            ->where('object_id', $objectId)
            ->where('title', $title);
        if ($exceptId > 0) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->exists()) {
            throw new InvalidArgumentException('Свойство с таким названием уже есть в этой области.');
        }
    }

    /**
     * @return array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string}
     */
    private function hydrate(object $row): array
    {
        // `??` терпит БД, где миграция полей ещё не применена: чтение значения
        // не должно падать из-за отсутствующей необязательной настройки.
        return [
            'id' => (int)$row->id,
            'class_id' => (int)$row->class_id,
            'object_id' => (int)($row->object_id ?? 0),
            'title' => (string)$row->title,
            'description' => (string)($row->description ?? ''),
            'data_type' => (string)($row->data_type ?? 'string'),
            'keep_history' => (int)($row->keep_history ?? 0) === 1,
            'on_change' => (string)($row->on_change ?? ''),
        ];
    }

    private function dataType(string $dataType): string
    {
        return in_array($dataType, ['string', 'int', 'float', 'bool'], true) ? $dataType : 'string';
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
