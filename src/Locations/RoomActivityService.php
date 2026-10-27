<?php

declare(strict_types=1);

namespace MajorDoMo\Locations;

use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Methods\MethodRunner;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Properties\ValueRepository;
use MajorDoMo\Runtime\TimeoutRepository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Активность комнаты: сбор ролей, флаги и таймер простоя.
 *
 * Правило владельца: комната активна, пока после последнего изменения
 * роль-свойства прошло меньше `activityTimeout`. Свет/обогрев/кондиционер
 * дополнительно складываются в `isLight`/`isHeating`/`isCondition`; датчик
 * только продлевает активность. Любое изменение роль-свойства перезапускает
 * таймер, поэтому долго включённый свет без событий не держит комнату
 * активной вечно — это осознанный выбор, а не недосмотр.
 *
 * Таймер — штатный `runtime_timeouts`: на комнату одна запись с именем
 * `room.activity.<id>`. Повторное событие снимает старую запись и ставит
 * новую; код таймера вызывает `expire()`. Просроченный таймер перечитывает
 * `lastActivityAt` и ничего не делает, если активность уже продлили.
 *
 * 2026-10-27 — сервис создан вместе с автоматическим учётом активности.
 */
final class RoomActivityService
{
    public const DEFAULT_TIMEOUT = 900;

    private const MAX_TIMEOUT = 2_592_000;

    private const SOURCE = 'room-activity';

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly LocationRepository $locations,
        private readonly ObjectRepository $objects,
        private readonly PropertyRepository $properties,
        private readonly ValueRepository $values,
        private readonly PropertyValueService $propertyValues,
        private readonly MethodRepository $methods,
        private readonly MethodRunner $methodsRunner,
        private readonly TimeoutRepository $timeouts,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Реакция на изменение свойства. Вызывается обработчиком `property.changed`
     * после сохранения значения и истории.
     *
     * @param array{id:int,title:string,class_id:int,location_id:int,description:string} $object
     * @param array{id:int,class_id:int,title:string,description:string,data_type:string,keep_history:bool,only_new_values:bool,on_change:string,room_role:string} $property
     */
    public function propertyChanged(array $object, array $property): void
    {
        $room = $this->locations->findRoomForObject((int)$object['id']);
        if ($room === null) {
            return;
        }

        $role = RoomRole::normalize((string)$property['room_role']);
        if ($role !== '') {
            $this->touch($room);

            return;
        }

        // Таймаут поменяли в карточке свойства или локации: старый таймер
        // мог быть рассчитан на другую длительность, поэтому пересчитываем.
        if ((string)$property['title'] === 'activityTimeout'
            && (int)$room['object_id'] === (int)$object['id']) {
            $this->reschedule($room);
        }
    }

    /**
     * Продлить активность и обновить флаги комнаты.
     *
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int} $room
     */
    public function touch(array $room): void
    {
        $roomObject = $this->roomObject($room);
        if ($roomObject === null) {
            return;
        }
        $roomObjectId = (int)$roomObject['id'];
        $timeout = $this->timeoutFor($room);
        $now = date('Y-m-d H:i:s');
        $activated = false;

        $this->database->transaction(function () use ($room, $roomObjectId, $now, &$activated): void {
            // Блокировка локации сериализует touch/expire одной комнаты:
            // иначе два события могли бы пересчитать флаги по разным снимкам.
            $this->database->connection()->table('locations')
                ->where('id', (int)$room['id'])
                ->lockForUpdate()
                ->first();
            $wasActive = $this->boolValue($roomObjectId, 'isActive');
            $activeRoles = $this->aggregate((int)$room['id']);
            $this->write($roomObjectId, 'lastActivityAt', $now);
            $this->applyFlags($roomObjectId, $activeRoles);
            if (!$wasActive && $this->write($roomObjectId, 'isActive', '1')) {
                $activated = true;
            }
        });

        $this->schedule($room, $timeout, time() + $timeout);
        if ($activated) {
            $this->callHook($roomObject, 'activity', [
                'LOCATION' => (string)$room['title'],
                'ROOM' => (string)$roomObject['title'],
                'AT' => $now,
            ]);
        }
    }

    /**
     * Завершить активность, если простой действительно истёк.
     *
     * Метод вызывается кодом `runtime_timeouts`; параметр `$scheduledFor`
     * оставлен для журнала и ручной диагностики.
     */
    public function expire(int $locationId, string $scheduledFor = ''): void
    {
        $room = $this->locations->find($locationId);
        if ($room === null || $room['kind'] !== LocationKind::ROOM) {
            return;
        }
        $roomObject = $this->roomObject($room);
        if ($roomObject === null) {
            return;
        }
        $roomObjectId = (int)$roomObject['id'];
        $deactivated = false;
        $extended = false;
        $timeout = $this->timeoutFor($room);
        $deadline = 0;
        $this->database->transaction(function () use ($room, $roomObjectId, &$deactivated, &$extended, &$timeout, &$deadline): void {
            $this->database->connection()->table('locations')
                ->where('id', (int)$room['id'])
                ->lockForUpdate()
                ->first();
            $timeout = $this->timeoutFor($room);
            $lastActivity = $this->read($roomObjectId, 'lastActivityAt') ?? '';
            $lastTimestamp = strtotime($lastActivity) ?: 0;
            if ($lastTimestamp > 0 && $lastTimestamp + $timeout > time()) {
                $extended = true;
                $deadline = $lastTimestamp + $timeout;

                return;
            }
            // Комнату могли покинуть объекты, не прислав событие: перед
            // завершением пересчитываем флаги по фактическому составу.
            $this->applyFlags($roomObjectId, $this->aggregate((int)$room['id']));
            if (!$this->boolValue($roomObjectId, 'isActive')) {
                return;
            }
            if ($this->write($roomObjectId, 'isActive', '0')) {
                $deactivated = true;
            }
        });

        if ($extended) {
            // Активность продлили после постановки таймера: даём новый срок,
            // не дожидаясь следующего события.
            $this->schedule($room, $timeout, $deadline);

            return;
        }
        if (!$deactivated) {
            return;
        }
        $this->callHook($roomObject, 'nonActivity', [
            'LOCATION' => (string)$room['title'],
            'ROOM' => (string)$roomObject['title'],
            'AT' => date('Y-m-d H:i:s'),
            'SCHEDULED_FOR' => $scheduledFor,
        ]);
    }

    /**
     * Пересчитать таймер по текущему activityTimeout и lastActivityAt.
     *
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int} $room
     */
    public function reschedule(array $room): void
    {
        $roomObject = $this->roomObject($room);
        if ($roomObject === null || !$this->boolValue((int)$roomObject['id'], 'isActive')) {
            return;
        }
        $lastTimestamp = strtotime($this->read((int)$roomObject['id'], 'lastActivityAt') ?? '') ?: 0;
        if ($lastTimestamp <= 0) {
            return;
        }
        $timeout = $this->timeoutFor($room);
        $deadline = $lastTimestamp + $timeout;
        if ($deadline <= time()) {
            $this->expire((int)$room['id']);

            return;
        }
        $this->schedule($room, $timeout, $deadline);
    }

    /**
     * Быстрое состояние комнаты по названию локации или объекта комнаты.
     *
     * @return array{
     *     location_id:int,title:string,object_id:int,object_title:string,
     *     active:bool,light:bool,heating:bool,condition:bool,
     *     timeout:int,last_activity_at:string
     * }|null
     */
    public function stateFor(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $room = $this->locations->findByTitle($name, LocationKind::ROOM);
        if ($room === null) {
            $object = $this->objects->findByTitle($name);
            if ($object === null) {
                return null;
            }
            $room = $this->locations->findRoomForObject((int)$object['id']);
        }

        return $room === null ? null : $this->stateForLocation($room);
    }

    /**
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int} $room
     * @return array{
     *     location_id:int,title:string,object_id:int,object_title:string,
     *     active:bool,light:bool,heating:bool,condition:bool,
     *     timeout:int,last_activity_at:string
     * }
     */
    public function stateForLocation(array $room): array
    {
        $roomObject = $this->roomObject($room);
        $roomObjectId = (int)($roomObject['id'] ?? 0);

        return [
            'location_id' => (int)$room['id'],
            'title' => (string)$room['title'],
            'object_id' => $roomObjectId,
            'object_title' => (string)($roomObject['title'] ?? ''),
            'active' => $roomObjectId > 0 && $this->boolValue($roomObjectId, 'isActive'),
            'light' => $roomObjectId > 0 && $this->boolValue($roomObjectId, 'isLight'),
            'heating' => $roomObjectId > 0 && $this->boolValue($roomObjectId, 'isHeating'),
            'condition' => $roomObjectId > 0 && $this->boolValue($roomObjectId, 'isCondition'),
            'timeout' => $this->timeoutFor($room),
            'last_activity_at' => $roomObjectId > 0 ? (string)($this->read($roomObjectId, 'lastActivityAt') ?? '') : '',
        ];
    }

    /**
     * Свойства с ролью, из которых сейчас складывается состояние комнаты.
     *
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int} $room
     * @return list<array{object_id:int,object_title:string,property_id:int,property_title:string,role:string,role_label:string,value:string,active:bool}>
     */
    public function sources(array $room): array
    {
        $rows = [];
        foreach ($this->roomObjects((int)$room['id']) as $object) {
            foreach ($this->properties->forObject((int)$object->id) as $property) {
                $role = RoomRole::normalize((string)$property['room_role']);
                if ($role === '') {
                    continue;
                }
                $value = (string)($this->values->get((int)$object->id, (int)$property['id']) ?? '');
                $rows[] = [
                    'object_id' => (int)$object->id,
                    'object_title' => (string)$object->title,
                    'property_id' => (int)$property['id'],
                    'property_title' => (string)$property['title'],
                    'role' => $role,
                    'role_label' => RoomRole::label($role),
                    'value' => $value,
                    'active' => self::isActiveValue($value),
                ];
            }
        }

        return $rows;
    }

    /**
     * Значение считается активным, если оно не пустое и не «выключено».
     *
     * Для датчиков это ненулевое число, для света — `1`/`on`/`true`.
     */
    public static function isActiveValue(string $value): bool
    {
        $value = mb_strtolower(trim($value));
        if ($value === '' || in_array($value, ['0', 'false', 'off', 'no', 'none', 'null', 'disabled', 'closed'], true)) {
            return false;
        }
        if (is_numeric($value)) {
            return (float)$value !== 0.0;
        }

        return true;
    }

    /**
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int} $room
     * @return array{id:int,title:string,class_id:int,location_id:int,description:string}|null
     */
    private function roomObject(array $room): ?array
    {
        $objectId = (int)$room['object_id'];

        return $objectId > 0 ? $this->objects->find($objectId) : null;
    }

    /**
     * @return list<\stdClass>
     */
    private function roomObjects(int $locationId): array
    {
        return $this->database->connection()->table('objects')
            ->where('location_id', $locationId)
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get(['id', 'title'])
            ->all();
    }

    /**
     * @return array<string,bool>
     */
    private function aggregate(int $locationId): array
    {
        $active = array_fill_keys(RoomRole::all(), false);
        foreach ($this->roomObjects($locationId) as $object) {
            foreach ($this->properties->forObject((int)$object->id) as $property) {
                $role = RoomRole::normalize((string)$property['room_role']);
                if ($role === '') {
                    continue;
                }
                $value = (string)($this->values->get((int)$object->id, (int)$property['id']) ?? '');
                if (self::isActiveValue($value)) {
                    $active[$role] = true;
                }
            }
        }

        return $active;
    }

    /**
     * @param array<string,bool> $activeRoles
     */
    private function applyFlags(int $roomObjectId, array $activeRoles): void
    {
        foreach (RoomRole::all() as $role) {
            $flag = RoomRole::roomFlag($role);
            if ($flag === null) {
                continue;
            }
            $desired = ($activeRoles[$role] ?? false) ? '1' : '0';
            $current = $this->read($roomObjectId, $flag);
            if ($current === null || $current === $desired) {
                continue;
            }
            $this->write($roomObjectId, $flag, $desired);
        }
    }

    /**
     * Одна запись таймера на комнату: старое имя снимается перед новой.
     *
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int} $room
     */
    private function schedule(array $room, int $timeout, ?int $deadline = null): void
    {
        $locationId = (int)$room['id'];
        $deadline ??= time() + $timeout;
        $scheduledFor = date('Y-m-d H:i:s', $deadline);
        $title = 'room.activity.' . $locationId;
        $code = '$app = app();' . "\n"
            . 'if ($app === null) { return null; }' . "\n"
            . 'return $app->container()->make(\\' . self::class . '::class)'
            . '->expire(' . $locationId . ', ' . var_export($scheduledFor, true) . ');';
        $this->timeouts->clear($title);
        $this->timeouts->set($title, $code, max(0, $deadline - time()));
    }

    /**
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int} $room
     */
    private function timeoutFor(array $room): int
    {
        $objectId = (int)$room['object_id'];
        $value = $objectId > 0 ? (int)($this->read($objectId, 'activityTimeout') ?? 0) : 0;
        if ($value <= 0) {
            $value = (int)$room['activity_timeout'];
        }

        return max(1, min(self::MAX_TIMEOUT, $value));
    }

    private function read(int $objectId, string $title): ?string
    {
        $property = $this->properties->resolveForObject($objectId, $title);
        if ($property === null) {
            return null;
        }

        return $this->values->get($objectId, (int)$property['id']);
    }

    private function boolValue(int $objectId, string $title): bool
    {
        return self::isActiveValue((string)($this->read($objectId, $title) ?? ''));
    }

    private function write(int $objectId, string $title, string $value): bool
    {
        $property = $this->properties->resolveForObject($objectId, $title);
        if ($property === null) {
            return false;
        }

        return $this->propertyValues->setValue($objectId, (int)$property['id'], $value, self::SOURCE);
    }

    /**
     * @param array{id:int,title:string,class_id:int,location_id:int,description:string} $roomObject
     * @param array<string,mixed> $params
     */
    private function callHook(array $roomObject, string $method, array $params): void
    {
        if ($this->methods->findForObject((int)$roomObject['id'], (int)$roomObject['class_id'], $method) === null) {
            return;
        }
        try {
            $this->methodsRunner->run((string)$roomObject['title'], $method, $params);
        } catch (Throwable $error) {
            $this->logger->warning('Хук активности комнаты завершился ошибкой', [
                'room' => (string)$roomObject['title'],
                'method' => $method,
                'error' => $error->getMessage(),
            ]);
        }
    }
}
