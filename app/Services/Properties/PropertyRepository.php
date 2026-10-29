<?php

declare(strict_types=1);

namespace LaIOT\Services\Properties;

use InvalidArgumentException;
use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Classes\ClassRepository;
use LaIOT\Services\Locations\RoomRole;
use LaIOT\Services\Rename\NameReferenceUpdater;
use LaIOT\Support\Identifier;
use Throwable;

/**
 * Свойства классов и объектов LaIOT.
 *
 * 2026-10-27 — наследуемое свойство ищется одним запросом по всей цепочке.
 * 2026-10-27 — удаление свойства снимает его подписки на доставку.
 * 2026-10-27 — свойства ищутся по цепочке наследования классов; добавлены
 * объектные свойства (`object_id`), которые переопределяют классовое свойство
 * с тем же названием только у одного объекта.
 * 2026-10-27 — имя свойства латиницей и уникально в области: в классе с учётом
 * предков, у объекта — среди объектных и действующих классовых. Переопределение
 * одноимённым свойством больше не создаётся.
 * 2026-10-27 — у свойства появилась роль в комнате (`room_role`): по ней
 * система собирает свет, обогрев, охлаждение и датчики активности.
 */
final readonly class PropertyRepository
{
    public function __construct(
        private DatabaseManager $database,
        private ClassRepository $classes,
        private ?PropertySubscriptionRepository $subscriptions = null,
        private ?NameReferenceUpdater $references = null
    ) {
    }

    /**
     * @return list<array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,only_new_values:bool,on_change:string,room_role:string}>
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
     * @return list<array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,only_new_values:bool,on_change:string,room_role:string,origin:string,owner_class_id:int,owner_class_title:string}>
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
     * @return list<array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,only_new_values:bool,on_change:string,room_role:string,origin:string,owner_class_id:int,owner_class_title:string}>
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
     * @return list<array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,only_new_values:bool,on_change:string,room_role:string,origin:string,owner_class_id:int,owner_class_title:string}>
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
     * @return array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,only_new_values:bool,on_change:string,room_role:string}|null
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
     * @return array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,only_new_values:bool,on_change:string,room_role:string}|null
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
     * @return array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,only_new_values:bool,on_change:string,room_role:string}|null
     */
    public function resolveForClass(int $classId, string $title): ?array
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        $lineage = $this->classes->lineage($classId);
        $ids = array_map(static fn (array $class): int => (int)$class['id'], $lineage);
        if ($ids === []) {
            return null;
        }
        $rows = $this->database->connection()->table('properties')
            ->whereIn('class_id', $ids)
            ->where('object_id', 0)
            ->where('title', $title)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()->groupBy('class_id');
        foreach ($ids as $id) {
            $row = $rows->get($id)?->first();
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
        int $objectId = 0,
        bool $onlyNewValues = false,
        string $roomRole = ''
    ): int {
        [$classId, $objectId] = $this->scope($classId, $objectId);
        $title = Identifier::assert($title, 'свойства');
        $onChange = $this->onChange($onChange);
        $this->assertTitleFree($classId, $objectId, $title);
        $now = date('Y-m-d H:i:s');
        $data = [
            'class_id' => $classId,
            'object_id' => $objectId,
            'title' => $title,
            'description' => trim($description),
            'data_type' => $this->dataType($dataType),
            'keep_history' => $keepHistory ? 1 : 0,
            'on_change' => trim($onChange),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ];
        if ($this->hasColumn('properties', 'only_new_values')) {
            $data['only_new_values'] = $onlyNewValues ? 1 : 0;
        }
        if ($this->hasColumn('properties', 'room_role')) {
            $data['room_role'] = RoomRole::normalize($roomRole);
        } elseif (RoomRole::normalize($roomRole) !== '') {
            throw new InvalidArgumentException(
                'Поле room_role не создано в БД: примените миграции (php bin/laiot db:migrate).'
            );
        }

        return (int)$this->database->connection()->table('properties')->insertGetId($data);
    }

    public function update(
        int $id,
        int $classId,
        string $title,
        string $description,
        string $dataType,
        bool $keepHistory,
        string $onChange,
        int $objectId = 0,
        bool $onlyNewValues = false,
        string $roomRole = ''
    ): void {
        [$classId, $objectId] = $this->scope($classId, $objectId);
        $title = Identifier::assert($title, 'свойства');
        $onChange = $this->onChange($onChange);
        $current = $this->find($id);
        // Legacy-строку с уже существующим дублем можно исправить, не
        // переименовывая: иначе редактирование описания было бы невозможно.
        if ($current === null || $current['title'] !== $title) {
            $this->assertTitleFree($classId, $objectId, $title, $id);
        }

        $connection = $this->database->connection();
        try {
            $connection->transaction(function () use ($connection, $id, $classId, $objectId, $title, $description, $dataType, $keepHistory, $onChange, $onlyNewValues, $roomRole, $current): void {
                $data = [
                    'class_id' => $classId,
                    'object_id' => $objectId,
                    'title' => $title,
                    'description' => trim($description),
                    'data_type' => $this->dataType($dataType),
                    'keep_history' => $keepHistory ? 1 : 0,
                    'on_change' => trim($onChange),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                if ($this->hasColumn('properties', 'only_new_values')) {
                    $data['only_new_values'] = $onlyNewValues ? 1 : 0;
                }
                if ($this->hasColumn('properties', 'room_role')) {
                    $data['room_role'] = RoomRole::normalize($roomRole);
                } elseif (RoomRole::normalize($roomRole) !== '') {
                    throw new InvalidArgumentException(
                        'Поле room_role не создано в БД: примените миграции (php bin/laiot db:migrate).'
                    );
                }
                $connection->table('properties')
                    ->where('id', $id)
                    ->whereNull('deleted_at')
                    ->update($data);
                if ($current !== null && $current['title'] !== $title) {
                    $this->references?->propertyRenamed(
                        $id,
                        (int)$current['class_id'],
                        (int)$current['object_id'],
                        $current['title'],
                        $title
                    );
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
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('properties')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
        // Подписка на удалённое свойство больше не имеет смысла и не должна
        // оставаться в таблице доставки.
        $this->subscriptions?->deleteForProperty($id);
    }

    /**
     * @param list<int> $classIds
     * @return list<array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,only_new_values:bool,on_change:string,room_role:string,origin:string,owner_class_id:int,owner_class_title:string}>
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

    /**
     * Имена, занятые в области свойства: класс с предками и объект.
     *
     * @return list<string>
     */
    public function takenNames(int $classId, int $objectId, int $exceptId = 0): array
    {
        $names = [];
        foreach ($this->classLevelNames($classId, $exceptId, $objectId <= 0) as $title) {
            $names[mb_strtolower($title)] = $title;
        }
        if ($objectId > 0) {
            $query = $this->database->connection()->table('properties')
                ->where('object_id', $objectId)
                ->whereNull('deleted_at');
            if ($exceptId > 0) {
                $query->where('id', '<>', $exceptId);
            }
            foreach ($query->pluck('title') as $title) {
                $names[mb_strtolower((string)$title)] = (string)$title;
            }
        }

        return array_values($names);
    }

    /**
     * Уникальность в области: в классе — с учётом предков, у объекта —
     * среди объектных свойств и действующих классовых.
     */
    private function assertTitleFree(int $classId, int $objectId, string $title, int $exceptId = 0): void
    {
        $classOwner = $this->classLevelOwner($classId, $title, $exceptId, $objectId <= 0);
        if ($classOwner !== null) {
            throw new InvalidArgumentException(
                $objectId > 0
                    ? 'Свойство «' . $title . '» уже объявлено у класса «' . $classOwner . '»; '
                        . 'у объекта используйте другое имя.'
                    : 'Свойство «' . $title . '» уже объявлено у класса «' . $classOwner . '» в этой ветке наследования.'
            );
        }

        if ($objectId <= 0) {
            return;
        }

        $query = $this->database->connection()->table('properties')
            ->where('class_id', $classId)
            ->where('object_id', $objectId)
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
            ->whereNull('deleted_at');
        if ($exceptId > 0) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->exists()) {
            throw new InvalidArgumentException('Свойство с таким названием уже есть у этого объекта.');
        }
    }

    /**
     * Класс из цепочки, где уже объявлено такое классовое свойство.
     */
    private function classLevelOwner(int $classId, string $title, int $exceptId = 0, bool $withDescendants = true): ?string
    {
        if ($classId <= 0) {
            return null;
        }
        $ids = $this->classScopeIds($classId, $withDescendants);
        if ($ids === []) {
            return null;
        }

        $query = $this->database->connection()->table('properties')
            ->whereIn('class_id', $ids)
            ->where('object_id', 0)
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
            ->whereNull('deleted_at');
        if ($exceptId > 0) {
            $query->where('id', '<>', $exceptId);
        }
        $ownerId = $query->value('class_id');
        if ($ownerId === null) {
            return null;
        }

        return (string)($this->classes->find((int)$ownerId)['title'] ?? '');
    }

    /**
     * @return list<string>
     */
    private function classLevelNames(int $classId, int $exceptId = 0, bool $withDescendants = true): array
    {
        if ($classId <= 0) {
            return [];
        }
        $ids = $this->classScopeIds($classId, $withDescendants);
        if ($ids === []) {
            return [];
        }
        $query = $this->database->connection()->table('properties')
            ->whereIn('class_id', $ids)
            ->where('object_id', 0)
            ->whereNull('deleted_at')
            ->orderBy('title');
        if ($exceptId > 0) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->pluck('title')->map(static fn (mixed $title): string => (string)$title)->all();
    }

    /**
     * Класс, его предки и (для свойств класса) потомки: имя должно быть
     * уникальным во всей ветке, а не только на одном уровне.
     *
     * @return list<int>
     */
    private function classScopeIds(int $classId, bool $withDescendants): array
    {
        $ids = $this->classes->ancestorIds($classId);
        if ($withDescendants) {
            foreach ($this->classes->descendantIds($classId, false) as $id) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,only_new_values:bool,on_change:string,room_role:string}
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
            'only_new_values' => (int)($row->only_new_values ?? 0) === 1,
            'on_change' => (string)($row->on_change ?? ''),
            'room_role' => RoomRole::normalize((string)($row->room_role ?? '')),
        ];
    }

    private function dataType(string $dataType): string
    {
        return in_array($dataType, ['string', 'int', 'float', 'bool'], true) ? $dataType : 'string';
    }

    private function onChange(string $onChange): string
    {
        $onChange = trim($onChange);

        return $onChange === '' ? '' : Identifier::assert($onChange, 'OnChange-метода');
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            $schema = $this->database->connection()->getSchemaBuilder();

            return $schema->hasTable($table) && $schema->hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }
}
