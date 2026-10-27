<?php

declare(strict_types=1);

namespace MajorDoMo\Locations;

use InvalidArgumentException;
use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Support\Transliteration;
use Throwable;

/**
 * Автоматическая модель комнаты: класс Rooms, подкласс этажа и объект.
 *
 * Иерархия классов повторяет иерархию локаций:
 * `Rooms` → класс этажа (транслит названия) → объект комнаты (транслит
 * названия). Объект получает `location_id` комнаты, поэтому локация остаётся
 * принадлежностью объекта, а не класса.
 *
 * Значения `activityTimeout`, `isActive`, `isLight`, `isHeating`,
 * `isCondition` и `lastActivityAt` создаются у объекта сразу: это настройки
 * и состояние конкретной комнаты, а не класса.
 *
 * 2026-10-27 — подготовка комнаты создана вместе с иерархией локаций.
 */
final readonly class RoomProvisioner
{
    public const BASE_CLASS = 'Rooms';

    public const DEFAULT_ACTIVITY_TIMEOUT = 900;

    /**
     * Дефолтные свойства класса Rooms.
     *
     * Роль (`room_role`) у них пустая: это состояние самой комнаты, а не
     * источник активности. Иначе система считала бы isActive собственным
     * входом и продлевала бы активность бесконечно.
     *
     * @var list<array{title:string,description:string,data_type:string}>
     */
    private const DEFAULT_PROPERTIES = [
        [
            'title' => 'isActive',
            'description' => 'Комната активна: после последнего события прошло меньше activityTimeout.',
            'data_type' => 'bool',
        ],
        [
            'title' => 'isLight',
            'description' => 'В комнате включён хотя бы один источник со ролью «Свет».',
            'data_type' => 'bool',
        ],
        [
            'title' => 'isHeating',
            'description' => 'В комнате включён хотя бы один источник со ролью «Обогрев».',
            'data_type' => 'bool',
        ],
        [
            'title' => 'isCondition',
            'description' => 'В комнате включён хотя бы один источник со ролью «Кондиционер / охлаждение».',
            'data_type' => 'bool',
        ],
        [
            'title' => 'activityTimeout',
            'description' => 'Секунд простоя после последнего события, после которых комната становится неактивной.',
            'data_type' => 'int',
        ],
        [
            'title' => 'lastActivityAt',
            'description' => 'Время последнего события активности в комнате.',
            'data_type' => 'string',
        ],
    ];

    /**
     * Дефолтные методы-хуки. Пустой код — это точка расширения: система
     * вызывает метод, а пользователь добавляет реакцию в карточке метода.
     *
     * @var list<array{title:string,description:string}>
     */
    private const DEFAULT_METHODS = [
        [
            'title' => 'activity',
            'description' => 'Хук начала активности комнаты. Вызывается при переходе из неактивной в активную.',
        ],
        [
            'title' => 'nonActivity',
            'description' => 'Хук завершения активности. Вызывается, когда истёк activityTimeout простоя.',
        ],
    ];

    public function __construct(
        private DatabaseManager $database,
        private ClassRepository $classes,
        private PropertyRepository $properties,
        private MethodRepository $methods,
        private ObjectRepository $objects,
        private PropertyValueService $values
    ) {
    }

    /**
     * Подготовить модель для созданной локации.
     *
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int} $location
     */
    public function provision(array $location): void
    {
        $baseId = $this->ensureBaseClass();
        $kind = (string)$location['kind'];
        if ($kind === LocationKind::HOUSE) {
            return;
        }
        if ($kind === LocationKind::FLOOR) {
            $this->ensureFloorClass($location, $baseId);

            return;
        }
        if ($kind !== LocationKind::ROOM) {
            return;
        }
        if ((int)$location['object_id'] > 0) {
            return;
        }

        $classId = $this->roomClass($location, $baseId);
        $objectTitle = $this->uniqueObjectTitle((string)$location['title']);
        $objectId = $this->objects->create(
            $objectTitle,
            $classId,
            (int)$location['id'],
            'Комната «' . (string)$location['title'] . '»'
        );
        $this->database->connection()->table('locations')
            ->where('id', (int)$location['id'])
            ->update([
                'class_id' => $classId,
                'object_id' => $objectId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        $this->initializeRoomValues($location, $objectId, $classId);
    }

    /**
     * Переименовать связанный объект комнаты или класс этажа.
     *
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int} $location
     */
    public function rename(array $location, string $title): void
    {
        if ($location['kind'] === LocationKind::ROOM) {
            $objectId = (int)$location['object_id'];
            $object = $objectId > 0 ? $this->objects->find($objectId) : null;
            if ($object === null) {
                return;
            }
            $newTitle = $this->uniqueObjectTitle($title, $objectId);
            if ($newTitle === (string)$object['title']) {
                return;
            }
            $this->objects->update(
                $objectId,
                $newTitle,
                (int)$object['class_id'],
                (int)$location['id'],
                (string)$object['description']
            );

            return;
        }

        if ($location['kind'] !== LocationKind::FLOOR) {
            return;
        }
        $classId = (int)$location['class_id'];
        $class = $classId > 0 ? $this->classes->find($classId) : null;
        if ($class === null) {
            return;
        }
        $newTitle = $this->uniqueClassTitle($title, $classId);
        if ($newTitle === (string)$class['title']) {
            return;
        }
        $this->classes->update($classId, $newTitle, (int)$class['parent_id'], (string)$class['description']);
    }

    /**
     * Обновить activityTimeout у объекта комнаты после правки локации.
     *
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int} $location
     */
    public function syncTimeout(array $location): void
    {
        $objectId = (int)$location['object_id'];
        if ($objectId <= 0) {
            return;
        }
        $property = $this->properties->resolveForObject($objectId, 'activityTimeout');
        if ($property === null) {
            return;
        }
        $this->values->setValue(
            $objectId,
            (int)$property['id'],
            (string)max(1, (int)$location['activity_timeout']),
            'location'
        );
    }

    /**
     * Отвязать объекты помещения перед удалением локации.
     */
    public function detachObjects(int $locationId): void
    {
        if (!$this->hasTable('objects')) {
            return;
        }
        $this->database->connection()->table('objects')
            ->where('location_id', $locationId)
            ->whereNull('deleted_at')
            ->update(['location_id' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Архивировать автоматический объект удалённой комнаты.
     *
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int} $location
     */
    public function archiveRoomObject(array $location): void
    {
        $objectId = (int)$location['object_id'];
        if ($objectId > 0) {
            $this->objects->delete($objectId);
        }
    }

    /**
     * Архивировать класс удалённого этажа, если он больше не используется.
     *
     * Комнаты удаляются раньше этажа, поэтому их объекты уже архивированы;
     * прямой объект этажа или пользовательский подкласс оставляет класс.
     *
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int} $location
     */
    public function archiveFloorClass(array $location): void
    {
        $classId = (int)$location['class_id'];
        if ($classId <= 0 || $location['kind'] !== LocationKind::FLOOR) {
            return;
        }
        try {
            $this->classes->delete($classId);
        } catch (InvalidArgumentException) {
            // Класс ещё используется объектами или подклассами — оставляем.
        }
    }

    /**
     * Класс Rooms создаётся один раз и получает дефолтные свойства/методы.
     */
    public function ensureBaseClass(): int
    {
        $base = $this->findClassByTitle(self::BASE_CLASS);
        $baseId = $base['id'] ?? $this->classes->create(
            self::BASE_CLASS,
            0,
            'Базовый класс помещений: активность, свет, обогрев и кондиционер.'
        );
        $this->ensureDefaults((int)$baseId);

        return (int)$baseId;
    }

    /**
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int} $floor
     */
    private function ensureFloorClass(array $floor, int $baseId): int
    {
        $classId = (int)$floor['class_id'];
        if ($classId > 0 && $this->classes->find($classId) !== null) {
            return $classId;
        }

        $title = $this->uniqueClassTitle((string)$floor['title']);
        $existing = $this->findClassByTitle($title);
        if ($existing !== null) {
            $classId = $existing['id'];
        } else {
            $classId = $this->classes->create(
                $title,
                $baseId,
                'Этаж: ' . (string)$floor['title']
            );
        }
        $this->database->connection()->table('locations')
            ->where('id', (int)$floor['id'])
            ->update(['class_id' => $classId, 'updated_at' => date('Y-m-d H:i:s')]);

        return $classId;
    }

    /**
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int} $room
     */
    private function roomClass(array $room, int $baseId): int
    {
        $parentId = (int)$room['parent_id'];
        if ($parentId > 0) {
            $parent = $this->database->connection()->table('locations')
                ->where('id', $parentId)
                ->whereNull('deleted_at')
                ->first();
            if ($parent !== null && (string)($parent->kind ?? '') === LocationKind::FLOOR) {
                return $this->ensureFloorClass([
                    'id' => (int)$parent->id,
                    'title' => (string)$parent->title,
                    'priority' => (int)($parent->priority ?? 0),
                    'parent_id' => (int)($parent->parent_id ?? 0),
                    'kind' => LocationKind::FLOOR,
                    'class_id' => (int)($parent->class_id ?? 0),
                    'object_id' => (int)($parent->object_id ?? 0),
                    'activity_timeout' => (int)($parent->activity_timeout ?? 0),
                ], $baseId);
            }
        }

        return $baseId;
    }

    /**
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int} $room
     */
    private function initializeRoomValues(array $room, int $objectId, int $classId): void
    {
        $timeout = max(1, (int)$room['activity_timeout']);
        $values = [
            'isActive' => '0',
            'isLight' => '0',
            'isHeating' => '0',
            'isCondition' => '0',
            'activityTimeout' => (string)$timeout,
            'lastActivityAt' => '',
        ];
        foreach ($values as $title => $value) {
            $property = $this->properties->resolveForClass($classId, $title);
            if ($property === null) {
                continue;
            }
            $this->values->setValue($objectId, (int)$property['id'], $value, 'location');
        }
    }

    private function ensureDefaults(int $classId): void
    {
        $propertyTitles = array_map(
            static fn (array $row): string => mb_strtolower((string)$row['title']),
            $this->properties->ownForClass($classId)
        );
        foreach (self::DEFAULT_PROPERTIES as $property) {
            if (in_array(mb_strtolower($property['title']), $propertyTitles, true)) {
                continue;
            }
            $this->properties->create(
                $classId,
                $property['title'],
                $property['description'],
                $property['data_type'],
                false,
                ''
            );
        }

        $methodTitles = array_map(
            static fn (array $row): string => mb_strtolower((string)$row['title']),
            $this->methods->ownForClass($classId)
        );
        foreach (self::DEFAULT_METHODS as $method) {
            if (in_array(mb_strtolower($method['title']), $methodTitles, true)) {
                continue;
            }
            $this->methods->create($classId, $method['title'], $method['description'], '');
        }
    }

    /**
     * @return array{id:int,title:string,parent_id:int,description:string}|null
     */
    private function findClassByTitle(string $title): ?array
    {
        $needle = mb_strtolower(trim($title));
        foreach ($this->classes->all() as $class) {
            if (mb_strtolower((string)$class['title']) === $needle) {
                return $class;
            }
        }

        return null;
    }

    private function uniqueObjectTitle(string $title, int $exceptId = 0): string
    {
        $taken = array_map(
            static fn (string $value): string => mb_strtolower($value),
            $this->objects->titles($exceptId)
        );

        return Transliteration::unique(
            Transliteration::identifier($title, 'Room'),
            static fn (string $candidate): bool => in_array(mb_strtolower($candidate), $taken, true)
        );
    }

    private function uniqueClassTitle(string $title, int $exceptId = 0): string
    {
        $taken = array_map(
            static fn (string $value): string => mb_strtolower($value),
            $this->classes->titles($exceptId)
        );

        return Transliteration::unique(
            Transliteration::identifier($title, 'Floor'),
            static fn (string $candidate): bool => in_array(mb_strtolower($candidate), $taken, true)
        );
    }

    private function hasTable(string $table): bool
    {
        try {
            return $this->database->connection()->getSchemaBuilder()->hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
