<?php

declare(strict_types=1);

namespace MajorDoMo\Locations;

use InvalidArgumentException;
use MajorDoMo\Database\DatabaseManager;
use Throwable;

/**
 * Локации MyDoMo: дом → этаж → комната.
 *
 * Локация остаётся справочником помещений, но теперь хранит место в дереве и
 * ссылки на автоматически созданные класс/объект комнаты. Класс и объект
 * создаёт `RoomProvisioner`: репозиторий отвечает за запись и проверки, а
 * подготовка модели дома остаётся отдельной операцией.
 *
 * Тип и родитель задаются при создании и не меняются при редактировании:
 * перенос комнаты между этажами — это отдельная операция с перепривязкой
 * класса, объекта и значений, а не молчаливая смена parent_id.
 *
 * 2026-10-27 — добавлены иерархия, тип, таймаут активности и авто-комнаты.
 * 2026-10-26 — добавлены поиск и количество объектов в помещении.
 */
final readonly class LocationRepository
{
    private const MAX_TITLE_LENGTH = 191;

    private const DEFAULT_ACTIVITY_TIMEOUT = 900;

    private const MAX_ACTIVITY_TIMEOUT = 2_592_000;

    public function __construct(
        private DatabaseManager $database,
        private ?RoomProvisioner $provisioner = null
    ) {
    }

    /**
     * @return list<array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int}>
     */
    public function all(string $search = ''): array
    {
        $query = $this->database->connection()->table('locations')->whereNull('deleted_at');
        if ($search !== '') {
            $query->where('title', 'like', '%' . $search . '%');
        }

        return $query->orderByDesc('priority')
            ->orderBy('title')
            ->get()
            ->map(fn (object $row): array => $this->hydrate($row))
            ->all();
    }

    /**
     * Плоское дерево для таблицы админки: дом, под ним этажи и комнаты.
     *
     * При поиске возвращаются только совпавшие локации без раскрытия дерева:
     * искать родителя по совпадению ребёнка пользователю не нужно.
     *
     * @return list<array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int,depth:int,path:string,has_children:bool,children_count:int}>
     */
    public function flattened(string $search = ''): array
    {
        $locations = $this->all();
        $search = trim($search);
        if ($search !== '') {
            $needle = mb_strtolower($search);

            return array_values(array_map(
                fn (array $location): array => $this->decorate($location, 0, $this->pathOf($locations, (int)$location['id']), false, 0),
                array_filter(
                    $locations,
                    static fn (array $location): bool => str_contains(mb_strtolower((string)$location['title']), $needle)
                )
            ));
        }

        $byId = [];
        foreach ($locations as $location) {
            $byId[(int)$location['id']] = $location;
        }
        $children = [];
        $roots = [];
        foreach ($locations as $location) {
            $parentId = (int)$location['parent_id'];
            if ($parentId > 0 && isset($byId[$parentId])) {
                $children[$parentId][] = (int)$location['id'];
            } else {
                $roots[] = (int)$location['id'];
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
            $this->sortIds($childIds, $byId);
            $path = implode(' → ', array_map(
                static fn (int $ancestorId): string => (string)($byId[$ancestorId]['title'] ?? ''),
                $ancestors
            ));
            $rows[] = $this->decorate(
                $byId[$id],
                $depth,
                $path,
                $childIds !== [],
                count($childIds)
            );
            foreach ($childIds as $childId) {
                $append($childId, $depth + 1, [...$ancestors, $id]);
            }
        };
        $this->sortIds($roots, $byId);
        foreach ($roots as $rootId) {
            $append($rootId, 0, []);
        }
        // Битый parent_id не должен скрывать локацию из списка.
        foreach ($locations as $location) {
            if (!isset($visited[$location['id']])) {
                $append((int)$location['id'], 0, []);
            }
        }

        return $rows;
    }

    /**
     * Количество объектов в каждой локации.
     *
     * Объект самой комнаты не считается: он создан автоматически и не
     * является оборудованием помещения.
     *
     * @return array<int,int>
     */
    public function objectCounts(): array
    {
        $counts = [];
        $rows = $this->database->connection()->table('objects')
            ->whereNull('deleted_at')
            ->select('location_id')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('location_id')
            ->get();
        foreach ($rows as $row) {
            $counts[(int)$row->location_id] = (int)$row->aggregate;
        }

        if ($this->hasColumn('locations', 'object_id')) {
            $auto = $this->database->connection()->table('locations')
                ->whereNull('deleted_at')
                ->where('object_id', '>', 0)
                ->get(['id', 'object_id']);
            foreach ($auto as $location) {
                $locationId = (int)$location->id;
                if (($counts[$locationId] ?? 0) <= 0) {
                    continue;
                }
                $belongs = $this->database->connection()->table('objects')
                    ->where('id', (int)$location->object_id)
                    ->where('location_id', $locationId)
                    ->whereNull('deleted_at')
                    ->exists();
                if ($belongs) {
                    $counts[$locationId]--;
                }
            }
        }

        return $counts;
    }

    /**
     * @return array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int}|null
     */
    public function find(int $id): ?array
    {
        $row = $this->database->connection()->table('locations')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * Поиск локации по названию; тип ограничивает выборку при необходимости.
     *
     * @return array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int}|null
     */
    public function findByTitle(string $title, string $kind = ''): ?array
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }
        $query = $this->database->connection()->table('locations')
            ->where('title', $title)
            ->whereNull('deleted_at');
        if ($kind !== '' && $this->hasColumn('locations', 'kind')) {
            $query->where('kind', LocationKind::normalize($kind));
        }
        $row = $query->first();
        if ($row === null) {
            // Названия локаций кириллические, а SQL LOWER в SQLite не знает
            // Unicode. Поэтому регистронезависимый запасной поиск идёт в PHP.
            $needle = mb_strtolower($title);
            $candidatesQuery = $this->database->connection()->table('locations')
                ->whereNull('deleted_at');
            if ($kind !== '' && $this->hasColumn('locations', 'kind')) {
                $candidatesQuery->where('kind', LocationKind::normalize($kind));
            }
            $candidates = $candidatesQuery->get();
            foreach ($candidates as $candidate) {
                if (mb_strtolower((string)$candidate->title) === $needle) {
                    $row = $candidate;
                    break;
                }
            }
        }

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * Комната, которой принадлежит объект. Для объекта вне комнаты — null.
     *
     * @return array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int}|null
     */
    public function findRoomForObject(int $objectId): ?array
    {
        if ($objectId <= 0 || !$this->hasTable('objects') || !$this->hasTable('locations')) {
            return null;
        }
        $locationId = (int)$this->database->connection()->table('objects')
            ->where('id', $objectId)
            ->whereNull('deleted_at')
            ->value('location_id');
        if ($locationId <= 0) {
            return null;
        }
        $location = $this->find($locationId);

        return $location !== null && $location['kind'] === LocationKind::ROOM ? $location : null;
    }

    /**
     * @return list<array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int}>
     */
    public function children(int $parentId): array
    {
        if ($parentId <= 0 || !$this->hasColumn('locations', 'parent_id')) {
            return [];
        }

        return $this->database->connection()->table('locations')
            ->where('parent_id', $parentId)
            ->whereNull('deleted_at')
            ->orderByDesc('priority')
            ->orderBy('title')
            ->get()
            ->map(fn (object $row): array => $this->hydrate($row))
            ->all();
    }

    /**
     * Создать локацию и, для комнаты/этажа, необходимую модель дома.
     *
     * @param string $kind Тип из LocationKind; по умолчанию комната, чтобы
     *                     старый вызов с двумя аргументами оставался осмысленным.
     */
    public function create(
        string $title,
        int $priority = 0,
        int $parentId = 0,
        string $kind = LocationKind::ROOM,
        int $activityTimeout = self::DEFAULT_ACTIVITY_TIMEOUT
    ): int {
        $title = $this->validateTitle($title);
        $kind = LocationKind::assert($kind);
        $parentId = max(0, $parentId);
        $this->assertPlacement($parentId, $kind);
        if ($this->provisioner !== null && !$this->hasColumn('locations', 'object_id')) {
            throw new InvalidArgumentException(
                'Схема локаций не обновлена: примените миграции (php bin/mydomo db:migrate).'
            );
        }
        $activityTimeout = $kind === LocationKind::ROOM ? $this->normalizeTimeout($activityTimeout) : 0;
        $now = date('Y-m-d H:i:s');
        $connection = $this->database->connection();
        $insert = function () use ($connection, $title, $priority, $parentId, $kind, $activityTimeout, $now): int {
            $data = [
                'title' => $title,
                'priority' => $priority,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
            foreach ([
                'parent_id' => $parentId,
                'kind' => $kind,
                'class_id' => 0,
                'object_id' => 0,
                'activity_timeout' => $activityTimeout,
            ] as $column => $value) {
                if ($this->hasColumn('locations', $column)) {
                    $data[$column] = $value;
                }
            }

            return (int)$connection->table('locations')->insertGetId($data);
        };

        if ($this->provisioner === null || !$this->hasColumn('locations', 'object_id')) {
            return $insert();
        }
        $provisioner = $this->provisioner;

        return $connection->transaction(function () use ($insert, $provisioner): int {
            $id = $insert();
            $location = $this->find($id);
            if ($location !== null) {
                $provisioner->provision($location);
            }

            return $id;
        });
    }

    /**
     * Редактирование названия, приоритета и таймаута комнаты.
     *
     * Тип и родитель здесь не меняются: перенос между этажами требует
     * перепривязки класса и объекта, поэтому это отдельная операция.
     * Нулевой `$activityTimeout` сохраняет текущее значение — так старый
     * вызов с тремя аргументами не сбрасывает настройку комнаты.
     */
    public function update(int $id, string $title, int $priority, int $activityTimeout = 0): void
    {
        $current = $this->find($id);
        if ($current === null) {
            return;
        }
        $title = $this->validateTitle($title);
        $timeout = $current['kind'] === LocationKind::ROOM
            ? ($activityTimeout > 0 ? $this->normalizeTimeout($activityTimeout) : (int)$current['activity_timeout'])
            : (int)$current['activity_timeout'];

        $connection = $this->database->connection();
        $provisioner = $this->provisioner;
        $connection->transaction(function () use ($connection, $current, $id, $title, $priority, $timeout, $provisioner): void {
            $data = [
                'title' => $title,
                'priority' => $priority,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($this->hasColumn('locations', 'activity_timeout')) {
                $data['activity_timeout'] = $timeout;
            }
            $connection->table('locations')
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->update($data);

            if ($provisioner === null) {
                return;
            }
            if ((string)$current['title'] !== $title) {
                $provisioner->rename($current, $title);
            }
            if ($current['kind'] === LocationKind::ROOM && (int)$current['activity_timeout'] !== $timeout) {
                $updated = $this->find($id);
                if ($updated !== null) {
                    $provisioner->syncTimeout($updated);
                }
            }
        });
    }

    /**
     * Удалить локацию. Родитель с вложенными локациями не удаляется.
     *
     * Объекты помещения отвязываются, а автоматический объект комнаты
     * архивируется: иначе после удаления оставались бы «висячие» имена.
     */
    public function delete(int $id): void
    {
        $location = $this->find($id);
        if ($location === null) {
            return;
        }
        if ($this->children($id) !== []) {
            throw new InvalidArgumentException('Сначала удалите или перенесите вложенные локации.');
        }

        $now = date('Y-m-d H:i:s');
        $connection = $this->database->connection();
        $provisioner = $this->provisioner;
        $connection->transaction(function () use ($connection, $location, $now, $provisioner): void {
            if ($provisioner !== null) {
                $provisioner->detachObjects((int)$location['id']);
                $provisioner->archiveRoomObject($location);
                $provisioner->archiveFloorClass($location);
            } elseif ($this->hasTable('objects')) {
                $connection->table('objects')
                    ->where('location_id', (int)$location['id'])
                    ->whereNull('deleted_at')
                    ->update(['location_id' => 0, 'updated_at' => $now]);
            }
            $connection->table('locations')
                ->where('id', (int)$location['id'])
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now, 'updated_at' => $now]);
        });
    }

    /**
     * Возможные родители для формы создания.
     *
     * @return list<array{id:int,title:string,kind:string,depth:int}>
     */
    public function parentOptions(): array
    {
        $rows = $this->flattened();

        return array_values(array_map(
            static fn (array $row): array => [
                'id' => (int)$row['id'],
                'title' => (string)$row['title'],
                'kind' => (string)$row['kind'],
                'depth' => (int)$row['depth'],
            ],
            array_filter(
                $rows,
                static fn (array $row): bool => in_array($row['kind'], [LocationKind::HOUSE, LocationKind::FLOOR], true)
            )
        ));
    }

    private function assertPlacement(int $parentId, string $kind): void
    {
        if ($parentId === 0) {
            // Дом, этаж и комната могут быть верхним уровнем: старые
            // помещения не должны требовать обязательного дома.
            return;
        }
        $parent = $this->find($parentId);
        if ($parent === null) {
            throw new InvalidArgumentException('Родительская локация не найдена.');
        }
        if (!LocationKind::canBeParent((string)$parent['kind'], $kind)) {
            throw new InvalidArgumentException(
                'Локацию «' . LocationKind::label($kind) . '» нельзя вложить в «'
                . LocationKind::label((string)$parent['kind']) . '».'
            );
        }
    }

    private function normalizeTimeout(int $seconds): int
    {
        if ($seconds <= 0) {
            return self::DEFAULT_ACTIVITY_TIMEOUT;
        }

        return min(self::MAX_ACTIVITY_TIMEOUT, $seconds);
    }

    private function validateTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new InvalidArgumentException('Название локации обязательно, до ' . self::MAX_TITLE_LENGTH . ' символа.');
        }

        return $title;
    }

    /**
     * @param list<int> $ids
     * @param array<int,array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int}> $byId
     */
    private function sortIds(array &$ids, array $byId): void
    {
        usort($ids, static function (int $left, int $right) use ($byId): int {
            $a = $byId[$left] ?? null;
            $b = $byId[$right] ?? null;
            if ($a === null || $b === null) {
                return $left <=> $right;
            }
            $priority = (int)$b['priority'] <=> (int)$a['priority'];

            return $priority !== 0 ? $priority : strnatcasecmp((string)$a['title'], (string)$b['title']);
        });
    }

    /**
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int} $location
     * @return array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int,depth:int,path:string,has_children:bool,children_count:int}
     */
    private function decorate(
        array $location,
        int $depth,
        string $path,
        bool $hasChildren,
        int $childrenCount
    ): array {
        return $location + [
            'depth' => $depth,
            'path' => $path === '' ? (string)$location['title'] : $path . ' → ' . (string)$location['title'],
            'has_children' => $hasChildren,
            'children_count' => $childrenCount,
        ];
    }

    /**
     * @param list<array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int}> $locations
     */
    private function pathOf(array $locations, int $id): string
    {
        $byId = [];
        foreach ($locations as $location) {
            $byId[(int)$location['id']] = $location;
        }
        $titles = [];
        $seen = [];
        $current = $id;
        while ($current > 0 && isset($byId[$current]) && !isset($seen[$current])) {
            $seen[$current] = true;
            $titles[] = (string)$byId[$current]['title'];
            $current = (int)$byId[$current]['parent_id'];
        }

        return implode(' → ', array_reverse($titles));
    }

    /**
     * @return array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int}
     */
    private function hydrate(object $row): array
    {
        // `??` терпит БД, где миграция иерархии ещё не применена.
        return [
            'id' => (int)$row->id,
            'title' => (string)$row->title,
            'priority' => (int)($row->priority ?? 0),
            'parent_id' => (int)($row->parent_id ?? 0),
            'kind' => LocationKind::normalize((string)($row->kind ?? LocationKind::ROOM)),
            'class_id' => (int)($row->class_id ?? 0),
            'object_id' => (int)($row->object_id ?? 0),
            'activity_timeout' => (int)($row->activity_timeout ?? self::DEFAULT_ACTIVITY_TIMEOUT),
        ];
    }

    private function hasTable(string $table): bool
    {
        try {
            return $this->database->connection()->getSchemaBuilder()->hasTable($table);
        } catch (Throwable) {
            return false;
        }
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
