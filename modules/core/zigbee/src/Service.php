<?php

declare(strict_types=1);

namespace LaIOT\Zigbee;

use InvalidArgumentException;
use LaIOT\Services\Classes\ClassRepository;
use LaIOT\Services\Locations\LocationRepository;
use LaIOT\Services\Methods\MethodRepository;
use LaIOT\Services\Methods\MethodRunner;
use LaIOT\Services\Modules\ModuleSubscriptions;
use LaIOT\Mqtt\PublisherInterface;
use LaIOT\Services\Objects\ObjectRepository;
use LaIOT\Services\Properties\PropertyValueService;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Логика Zigbee2MQTT: discovery, привязки, команды и комнаты.
 *
 * Сообщение приходит из MQTT-цикла уже в виде задания `module.zigbee`, поэтому
 * этот сервис не держит соединение и не ждёт брокера. Все изменения своих
 * таблиц идут через Repository/ModuleDb; значения объектов — только через
 * PropertyValueService, методы — через MethodRunner.
 *
 * Порядок обработки сообщения устройства:
 * исходное значение → правило приёма → запись свойства → OnChange ядра →
 * дополнительный метод → уведомление комнаты. Ошибка одного свойства не
 * останавливает остальные, но после обработки всех поднимается наверх, чтобы
 * очередь повторила задание.
 *
 * 2026-10-28 — перенос alpha `Service` на сервисы нового ядра.
 */
final class Service
{
    /** @var list<string>|null Кэш корневых топиков: owns() вызывается на каждое сообщение. */
    private ?array $roots = null;

    private ?int $roomsClassId = null;

    private bool $roomsClassResolved = false;

    public function __construct(
        private readonly Repository $repository,
        private readonly PublisherInterface $publisher,
        private readonly PropertyValueService $properties,
        private readonly MethodRunner $methods,
        private readonly MethodRepository $methodRepository,
        private readonly ClassRepository $classes,
        private readonly ObjectRepository $objects,
        private readonly LocationRepository $locations,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Фильтры для общего MQTT-соединения.
     *
     * @return list<string>
     */
    public function topics(): array
    {
        $topics = [];
        $roots = [];
        foreach ($this->enabledBridges() as $bridge) {
            $base = (string)$bridge['base_topic'];
            $roots[] = $base;
            $topics[] = $base . '/#';
        }
        $this->roots = $roots;

        return $topics;
    }

    public function owns(string $topic): bool
    {
        // MQTT-цикл спрашивает владельца на каждое сообщение. Список корней
        // обновляется при подписке (topics), поэтому лишний SQL здесь не нужен.
        if ($this->roots === null) {
            $this->topics();
        }
        foreach ($this->roots ?? [] as $base) {
            if (str_starts_with($topic, $base . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Обрабатывает одно сообщение брокера.
     */
    public function receive(string $topic, string $payload): void
    {
        foreach ($this->enabledBridges() as $bridge) {
            if (!str_starts_with($topic, (string)$bridge['base_topic'] . '/')) {
                continue;
            }
            $suffix = substr($topic, strlen((string)$bridge['base_topic']) + 1);
            try {
                if (str_starts_with($suffix, 'bridge/')) {
                    $this->bridgeMessage($bridge, $suffix, $payload);

                    return;
                }
                if (preg_match('#/(set|get)(/.*)?$#D', $suffix) === 1) {
                    return;
                }
                $this->deviceMessage($bridge, $suffix, $payload, $topic);

                return;
            } catch (Throwable $error) {
                $this->repository->log((int)$bridge['id'], null, $topic, $error->getMessage(), 'error');
                throw $error;
            }
        }
    }

    /**
     * @param array<string, mixed> $bridge
     */
    private function deviceMessage(array $bridge, string $suffix, string $payload, string $topic): void
    {
        $availability = str_ends_with($suffix, '/availability');
        $name = $availability ? substr($suffix, 0, -13) : $suffix;
        if ($availability) {
            // Zigbee2MQTT присылает либо строку online/offline, либо объект
            // {"state":...}. JSON-разбор всего потока тут не подходит.
            $decoded = json_decode($payload, true, 8);
            $state = is_array($decoded) ? ($decoded['state'] ?? '') : $payload;
            if (!in_array($state, ['online', 'offline'], true)) {
                throw new InvalidArgumentException('Неизвестный статус доступности.');
            }
            $device = $this->repository->device((int)$bridge['id'], $name);
            $update = ['available' => $state];
            if ($state === 'online') {
                $update['last_seen'] = date('Y-m-d H:i:s');
            }
            $this->repository->saveDevice((int)$device['id'], $update);

            return;
        }

        $decoded = json_decode($payload, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !str_starts_with(ltrim($payload), '{')) {
            throw new InvalidArgumentException('Ожидался JSON-объект состояния устройства.');
        }
        $device = $this->repository->device((int)$bridge['id'], $name);
        $now = date('Y-m-d H:i:s');
        // Приём данных доказывает связь даже если обработчик свойства потом
        // упадёт: мониторинг не должен путать эти два события.
        $this->repository->saveDevice((int)$device['id'], ['available' => 'online', 'last_seen' => $now]);

        $values = self::flatten($decoded);
        $health = $this->repository->healthSettings($device);
        $metadata = ValueTransformer::decode((string)($device['metadata'] ?? '')) ?? [];
        $batteryPowered = str_contains(strtolower((string)($metadata['power_source'] ?? '')), 'battery');
        $reported = $values['battery'] ?? null;
        if ($health['battery_source'] !== 'off'
            && ($health['battery_source'] === 'voltage' || $batteryPowered)
            && !($health['battery_source'] === 'auto' && Battery::number($reported)
                && $reported >= 0 && $reported <= 100)) {
            $estimated = Battery::estimate($values[$health['voltage_path']] ?? null, $health);
            if ($estimated !== null) {
                $values['battery_estimated'] = $estimated;
                $this->repository->property((int)$device['id'], 'battery_estimated', [
                    'type' => 'numeric',
                    'property' => 'battery_estimated',
                    'label' => 'Заряд батареи (примерно)',
                    'unit' => '%',
                    'access' => 1,
                    'source' => 'voltage',
                ]);
            }
        }
        // Нажатие кнопки — событие, даже если действие повторяется. Обычное
        // свойство action остаётся, а action:<вид> меняет значение при каждом
        // приёме, поэтому OnChange срабатывает снова.
        if (isset($decoded['action']) && is_string($decoded['action']) && $decoded['action'] !== '') {
            $eventPath = 'action:' . $decoded['action'];
            if (strlen($eventPath) <= 190) {
                $values[$eventPath] = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
            }
        }

        $failures = [];
        foreach ($values as $path => $raw) {
            try {
                $this->deliver($bridge, $device, (string)$path, $raw, $topic);
            } catch (Throwable $error) {
                $failures[] = $path . ': ' . $error->getMessage();
            }
        }
        if ($failures !== []) {
            throw new RuntimeException(implode('; ', $failures));
        }
    }

    /**
     * @param array<string, mixed> $bridge
     */
    private function bridgeMessage(array $bridge, string $suffix, string $payload): void
    {
        $bridgeId = (int)$bridge['id'];
        if (str_starts_with($suffix, 'bridge/request/')
            || in_array($suffix, ['bridge/definitions', 'bridge/converters', 'bridge/extensions'], true)) {
            return;
        }
        $data = $suffix === 'bridge/state' && in_array($payload, ['online', 'offline'], true)
            ? $payload
            : json_decode($payload, true, 64, JSON_THROW_ON_ERROR);

        if ($suffix === 'bridge/devices') {
            if (!is_array($data) || !array_is_list($data)) {
                throw new InvalidArgumentException('Список устройств должен быть JSON-массивом.');
            }
            $replay = [];
            $this->repository->transaction(function () use ($bridgeId, $data, &$replay): void {
                foreach ($data as $metadata) {
                    if (!is_array($metadata) || empty($metadata['ieee_address'])) {
                        throw new InvalidArgumentException('В описании устройства нет IEEE-адреса.');
                    }
                    if (($metadata['type'] ?? '') === 'Coordinator') {
                        continue;
                    }
                    $name = (string)($metadata['friendly_name'] ?? $metadata['ieee_address']);
                    $device = $this->repository->device($bridgeId, $name, (string)$metadata['ieee_address']);
                    if (!empty($device['_replay'])) {
                        $replay[] = [$device, $device['_replay']];
                    }
                    $definition = is_array($metadata['definition'] ?? null) ? $metadata['definition'] : [];
                    $this->repository->saveDevice((int)$device['id'], [
                        'ieee_address' => strtolower((string)$metadata['ieee_address']),
                        'friendly_name' => $name,
                        'model' => mb_substr((string)($definition['model'] ?? $metadata['model_id'] ?? ''), 0, 190),
                        'vendor' => mb_substr((string)($definition['vendor'] ?? ''), 0, 190),
                        'description' => (string)($definition['description'] ?? ''),
                        'metadata' => ValueTransformer::json($metadata),
                    ]);
                    foreach (self::exposes($definition['exposes'] ?? []) as $path => $expose) {
                        $this->repository->property((int)$device['id'], $path, $expose);
                    }
                }
            });
            foreach ($replay as [$previousDevice, $values]) {
                $current = $this->repository->deviceById((int)$previousDevice['id']);
                if ($current === null) {
                    continue;
                }
                foreach ($values as $path => $raw) {
                    $this->deliver($bridge, $current, (string)$path, $raw, (string)$bridge['base_topic'] . '/' . $current['friendly_name']);
                }
            }
            $this->repository->log($bridgeId, null, $suffix, 'Обнаружение завершено. Устройств в сообщении: ' . count($data));

            return;
        }

        if ($suffix === 'bridge/state') {
            $state = is_array($data) ? ($data['state'] ?? '') : $data;
            if (!in_array($state, ['online', 'offline'], true)) {
                throw new InvalidArgumentException('Неизвестный статус Zigbee2MQTT.');
            }
            $this->repository->saveBridgeState($bridgeId, (string)$state);

            return;
        }

        if ($suffix === 'bridge/info') {
            $this->repository->saveBridgeInfo($bridgeId, ValueTransformer::json($data));

            return;
        }

        if ($suffix === 'bridge/event' || str_starts_with($suffix, 'bridge/response/') || $suffix === 'bridge/logging') {
            $level = is_array($data) && ((($data['status'] ?? '') === 'error') || (($data['level'] ?? '') === 'error'))
                ? 'error' : 'info';
            if ($suffix === 'bridge/event' && ($data['type'] ?? '') === 'device_leave'
                && !empty($data['data']['ieee_address'])) {
                $device = $this->repository->deviceByIeee($bridgeId, (string)$data['data']['ieee_address']);
                if ($device !== null) {
                    $this->repository->saveDevice((int)$device['id'], ['available' => 'offline']);
                }
            }
            $this->repository->log($bridgeId, null, $suffix, $payload, $level);
        }
    }

    /**
     * Применяет одно свойство сообщения.
     *
     * @param array<string, mixed> $bridge
     * @param array<string, mixed> $device
     */
    private function deliver(array $bridge, array $device, string $path, mixed $raw, string $topic): void
    {
        $property = $this->repository->property((int)$device['id'], $path);
        $previousValue = ValueTransformer::decode((string)($property['value'] ?? ''));
        $binding = $this->repository->binding((int)$property['id']);
        $this->repository->savePropertyValue((int)$property['id'], ValueTransformer::json($raw));
        try {
            $value = $binding !== null && (int)$binding['enabled'] === 1
                ? ValueTransformer::apply(
                    $raw,
                    ValueTransformer::decode((string)($binding['incoming'] ?? '')) ?? [],
                    ValueTransformer::decode((string)($property['expose'] ?? '')) ?? []
                )
                : $raw;
            $encoded = ValueTransformer::json($value);
            $this->repository->saveConvertedValue((int)$property['id'], $encoded);
            if ((string)$device['status'] !== 'added') {
                return;
            }
            $deliverBinding = $binding !== null
                && (int)$binding['enabled'] === 1
                && !((string)$binding['event_mode'] === 'change' && $binding['last_delivered'] === $encoded);
            $source = 'zigbee:' . $bridge['base_topic'] . '/' . $device['friendly_name'] . ':' . $path;
            $hasProperty = $binding !== null
                && (string)$binding['linked_object'] !== ''
                && (string)$binding['linked_property'] !== '';
            $hasMethod = $binding !== null
                && (string)$binding['linked_object'] !== ''
                && (string)$binding['linked_method'] !== '';
            // Значение сначала записывается в объект: и OnChange, и явный
            // метод должны видеть новое значение.
            if ($deliverBinding && $hasProperty) {
                $this->writeProperty($binding, $value, $source);
            }
            if ($deliverBinding && $hasMethod) {
                $this->methods->run(
                    (string)$binding['linked_object'],
                    (string)$binding['linked_method'],
                    [
                        'VALUE' => $value,
                        'NEW_VALUE' => $value,
                        'OLD_VALUE' => ValueTransformer::decode((string)($binding['last_delivered'] ?? '')),
                        'RAW_VALUE' => $raw,
                        'TITLE' => $path,
                        'SOURCE' => $source,
                        'IEEE_ADDRESS' => (string)($device['ieee_address'] ?? ''),
                        'BRIDGE_ID' => (int)$bridge['id'],
                    ]
                );
            }
            if ((int)$device['location_id'] > 0) {
                $this->notifyRoom($bridge, $device, $path, $value, $previousValue, $raw, $topic, $source);
            }
            if (!$deliverBinding && (int)$device['location_id'] === 0) {
                return;
            }
            if ($deliverBinding && $binding !== null) {
                $this->repository->saveBinding((int)$property['id'], [
                    'last_delivered' => $encoded,
                    'last_error' => null,
                ]);
            }
            $this->repository->log(
                (int)$bridge['id'],
                (int)$device['id'],
                $topic,
                $path . ': ' . ValueTransformer::json($raw) . ' → ' . $encoded . ' · обработано'
            );
        } catch (Throwable $error) {
            if ($binding !== null) {
                $this->repository->saveBinding((int)$property['id'], ['last_error' => $error->getMessage()]);
            }
            throw $error;
        }
    }

    /**
     * Отправляет значение в свойство объекта с учётом сохранённых id.
     *
     * @param array<string, mixed> $binding
     */
    private function writeProperty(array $binding, mixed $value, string $source): void
    {
        $objectId = (int)($binding['linked_object_id'] ?? 0);
        $propertyId = (int)($binding['linked_property_id'] ?? 0);
        $ok = $objectId > 0 && $propertyId > 0
            ? $this->properties->setValue($objectId, $propertyId, $value, $source)
            : $this->properties->set((string)$binding['linked_object'], (string)$binding['linked_property'], $value, $source);
        if (!$ok) {
            throw new RuntimeException('Запись свойства отклонена: '
                . $binding['linked_object'] . '.' . $binding['linked_property']);
        }
    }

    /**
     * Уведомляет комнату о каждом свойстве каждого сообщения.
     *
     * @param array<string, mixed> $bridge
     * @param array<string, mixed> $device
     */
    private function notifyRoom(
        array $bridge,
        array $device,
        string $path,
        mixed $value,
        mixed $oldValue,
        mixed $raw,
        string $topic,
        string $source
    ): void {
        $location = $this->locations->find((int)$device['location_id']);
        if ($location === null) {
            throw new RuntimeException('Комната устройства удалена. Выберите другую комнату.');
        }
        $roomObjectId = (int)($location['object_id'] ?? 0);
        $room = $roomObjectId > 0 ? $this->objects->find($roomObjectId) : null;
        if ($room === null) {
            throw new RuntimeException('У комнаты нет объекта для обработчика deviceChanged.');
        }
        $this->ensureRoomHook();
        $this->methods->run((string)$room['title'], 'deviceChanged', [
            'DEVICE_ID' => (int)$device['id'],
            'DEVICE_NAME' => (string)$device['friendly_name'],
            'DEVICE_TITLE' => (string)$device['title'],
            'IEEE_ADDRESS' => (string)($device['ieee_address'] ?? ''),
            'BRIDGE_ID' => (int)$bridge['id'],
            'BASE_TOPIC' => (string)$bridge['base_topic'],
            'LOCATION_ID' => (int)$device['location_id'],
            'TOPIC' => $topic,
            'PROPERTY' => $path,
            'TITLE' => $path,
            'VALUE' => $value,
            'NEW_VALUE' => $value,
            'OLD_VALUE' => $oldValue,
            'RAW_VALUE' => $raw,
            'SOURCE' => $source,
        ]);
    }

    /**
     * Создаёт `Rooms.deviceChanged`, если его ещё нет.
     *
     * Комнаты ядра получают объект в классе этажа с базовым классом `Rooms`.
     * Метод-хук создаётся один раз, как activity/nonActivity у RoomProvisioner;
     * существующий код не перезаписывается.
     */
    private function ensureRoomHook(): void
    {
        if (!$this->roomsClassResolved) {
            $this->roomsClassResolved = true;
            foreach ($this->classes->all() as $class) {
                if (mb_strtolower(trim((string)$class['title'])) === 'rooms') {
                    $this->roomsClassId = (int)$class['id'];
                    break;
                }
            }
        }
        if ($this->roomsClassId === null) {
            // Класса Rooms ещё нет (свежая установка без локаций): комната
            // будет создана позже, а сообщение сейчас просто доставляет данные.
            return;
        }
        if ($this->methodRepository->findForClass($this->roomsClassId, 'deviceChanged') !== null) {
            return;
        }
        try {
            $this->methodRepository->create(
                $this->roomsClassId,
                'deviceChanged',
                'Вызывается Zigbee для каждого свойства каждого сообщения устройства в комнате.',
                "// \$params: DEVICE_ID, DEVICE_NAME, PROPERTY, VALUE, RAW_VALUE и другие.\nreturn true;"
            );
        } catch (Throwable $error) {
            // Параллельный процесс мог создать метод первым: это не ошибка.
            if ($this->methodRepository->findForClass($this->roomsClassId, 'deviceChanged') === null) {
                $this->logger->warning('Zigbee: метод Rooms.deviceChanged не создан', [
                    'error' => $error->getMessage(),
                ]);
            }
        }
    }

    /**
     * Команда устройству из привязанного свойства.
     */
    public function propertyChanged(string $objectTitle, string $propertyTitle, mixed $value): void
    {
        foreach ($this->repository->writeBindings($objectTitle, $propertyTitle) as $binding) {
            $this->sendCommand(
                $value,
                $binding['property'],
                $binding['device'],
                $binding['bridge'],
                $binding
            );
        }
    }

    /**
     * Отправляет значение свойства в устройство.
     */
    public function write(int $propertyId, mixed $value): void
    {
        $property = $this->repository->propertyById($propertyId);
        if ($property === null) {
            throw new InvalidArgumentException('Свойство не найдено.');
        }
        $device = $this->repository->deviceById((int)$property['device_id']);
        if ($device === null) {
            throw new InvalidArgumentException('Устройство не найдено.');
        }
        $bridge = $this->repository->bridge((int)$device['bridge_id']);
        if ($bridge === null) {
            throw new InvalidArgumentException('Экземпляр Zigbee2MQTT не найден.');
        }
        $this->sendCommand($value, $property, $device, $bridge, $this->repository->binding($propertyId));
    }

    /**
     * @param array<string, mixed> $property
     * @param array<string, mixed> $device
     * @param array<string, mixed> $bridge
     * @param array<string, mixed>|null $binding
     */
    private function sendCommand(
        mixed $value,
        array $property,
        array $device,
        array $bridge,
        ?array $binding
    ): void {
        if ((int)$bridge['enabled'] !== 1) {
            throw new InvalidArgumentException('Экземпляр Zigbee2MQTT выключен.');
        }
        if ((string)$device['status'] !== 'added') {
            throw new InvalidArgumentException('Сначала добавьте устройство из раздела «Обнаруженные».');
        }
        $expose = ValueTransformer::decode((string)($property['expose'] ?? '')) ?? [];
        if (!isset($expose['access']) || !(((int)$expose['access']) & 2)) {
            throw new InvalidArgumentException('Устройство не разрешает запись этого свойства (exposes).');
        }
        $converted = ValueTransformer::apply(
            $value,
            ValueTransformer::decode((string)($binding['outgoing'] ?? '')) ?? [],
            $expose,
            true
        );
        if (($expose['type'] ?? '') === 'binary') {
            $accepted = false;
            foreach (['value_on', 'value_off', 'value_toggle'] as $key) {
                if (array_key_exists($key, $expose) && ValueTransformer::equal($converted, $expose[$key])) {
                    $accepted = true;
                }
            }
            if (!$accepted) {
                throw new InvalidArgumentException(
                    'Недопустимое бинарное значение устройства. Проверьте преобразование отправки.'
                );
            }
        }
        if (($expose['type'] ?? '') === 'numeric'
            && (!is_numeric($converted) || !is_finite((float)$converted))) {
            throw new InvalidArgumentException('Для этого свойства требуется конечное число.');
        }
        if (isset($expose['value_min'])
            && (!is_numeric($converted) || $converted < $expose['value_min'])) {
            throw new InvalidArgumentException('Значение ниже допустимого минимума.');
        }
        if (isset($expose['value_max'])
            && (!is_numeric($converted) || $converted > $expose['value_max'])) {
            throw new InvalidArgumentException('Значение выше допустимого максимума.');
        }
        if (isset($expose['values']) && is_array($expose['values'])
            && !in_array($converted, $expose['values'], true)) {
            throw new InvalidArgumentException('Значение отсутствует в списке допустимых.');
        }

        $data = $converted;
        foreach (array_reverse(explode('.', (string)$property['path'])) as $key) {
            $data = [$key => $data];
        }
        $topic = (string)$bridge['base_topic'] . '/' . $device['friendly_name'] . '/set';
        $this->publisher->publish($topic, ValueTransformer::json($data));
        $this->repository->log(
            (int)$bridge['id'],
            (int)$device['id'],
            (string)$property['path'],
            'Команда поставлена в очередь: ' . ValueTransformer::json($converted)
        );
    }

    /**
     * Команда Zigbee2MQTT через bridge/request.
     *
     * @param array<string, mixed> $data
     */
    public function command(int $bridgeId, string $command, array $data): void
    {
        $allowed = [
            'permit_join',
            'device/rename',
            'device/remove',
            'device/configure',
            'device/interview',
            'device/options',
            'health_check',
            'networkmap',
        ];
        if (!in_array($command, $allowed, true)) {
            throw new InvalidArgumentException('Неизвестная команда Zigbee2MQTT.');
        }
        $bridge = $this->repository->bridge($bridgeId);
        if ($bridge === null || (int)$bridge['enabled'] !== 1) {
            throw new InvalidArgumentException('Экземпляр Zigbee2MQTT выключен или не найден.');
        }
        $data['transaction'] = 'mjd-' . bin2hex(random_bytes(8));
        $this->publisher->publish(
            (string)$bridge['base_topic'] . '/bridge/request/' . $command,
            ValueTransformer::json($data)
        );
        $this->repository->log($bridgeId, null, $command, 'Запрос отправлен: ' . ValueTransformer::json($data));
    }

    /**
     * Добавляет выбранные устройства или назначает им комнату.
     *
     * @param list<int> $ids
     */
    public function configureDevices(array $ids, int $locationId, bool $adopt = false, ?string $title = null): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === [] || min($ids) <= 0) {
            throw new InvalidArgumentException('Выберите устройства.');
        }
        if ($title !== null && (count($ids) !== 1 || mb_strlen($title) > 190)) {
            throw new InvalidArgumentException('Название задаётся одному устройству, до 190 символов.');
        }
        if ($locationId < 0) {
            throw new InvalidArgumentException('Комната не найдена.');
        }
        $location = $locationId > 0 ? $this->locations->find($locationId) : null;
        if ($locationId > 0 && $location === null) {
            throw new InvalidArgumentException('Комната не найдена.');
        }
        if ($location !== null && (int)($location['object_id'] ?? 0) === 0) {
            throw new InvalidArgumentException('У выбранной локации нет объекта комнаты.');
        }

        return $this->repository->transaction(function () use ($ids, $locationId, $adopt, $title): int {
            $devices = $this->repository->devicesByIds($ids);
            if (count($devices) !== count($ids)) {
                throw new InvalidArgumentException('Одно из устройств больше не существует. Обновите страницу.');
            }
            $total = 0;
            foreach ($devices as $device) {
                if ($adopt && (string)$device['status'] === 'added') {
                    continue;
                }
                $values = ['location_id' => $locationId];
                if ($title !== null) {
                    $values['title'] = $title;
                }
                if ($adopt) {
                    $values['status'] = 'added';
                }
                $this->repository->saveDevice((int)$device['id'], $values);
                $total++;
            }

            return $total;
        });
    }

    /**
     * Обновляет название и комнату одного устройства.
     */
    public function updateDevice(int $id, string $title, int $locationId): void
    {
        $device = $this->repository->deviceById($id);
        if ($device === null) {
            throw new InvalidArgumentException('Устройство не найдено.');
        }
        $title = trim($title);
        if (mb_strlen($title) > 190) {
            throw new InvalidArgumentException('Название устройства — до 190 символов.');
        }
        if ($locationId > 0 && $this->locations->find($locationId) === null) {
            throw new InvalidArgumentException('Комната не найдена.');
        }
        $this->repository->saveDevice($id, ['title' => $title, 'location_id' => max(0, $locationId)]);
    }

    /**
     * Сохраняет привязку свойства и синхронизирует подписку модуля.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function saveBinding(int $propertyId, array $input, ModuleSubscriptions $subscriptions): array
    {
        $property = $this->repository->propertyById($propertyId);
        if ($property === null) {
            throw new InvalidArgumentException('Свойство устройства не найдено.');
        }
        $previous = $this->repository->binding($propertyId);
        $objectTitle = trim((string)($input['linked_object'] ?? ''));
        $propertyTitle = trim((string)($input['linked_property'] ?? ''));
        $methodTitle = trim((string)($input['linked_method'] ?? ''));
        $objectId = null;
        $linkedPropertyId = null;
        if ($objectTitle !== '') {
            $object = $this->objects->findByTitle($objectTitle);
            if ($object === null) {
                throw new InvalidArgumentException('Объект не найден: «' . $objectTitle . '».');
            }
            $objectId = (int)$object['id'];
            if ($propertyTitle !== '') {
                $linked = $this->properties->resolve($objectTitle, $propertyTitle);
                if ($linked === null) {
                    throw new InvalidArgumentException(
                        'Свойство «' . $propertyTitle . '» не действует у объекта «' . $objectTitle . '».'
                    );
                }
                $linkedPropertyId = (int)$linked['property']['id'];
            }
            if ($methodTitle !== '' && $this->methodRepository->findForObject(
                $objectId,
                (int)$object['class_id'],
                $methodTitle
            ) === null) {
                throw new InvalidArgumentException(
                    'Метод «' . $methodTitle . '» не найден у объекта «' . $objectTitle . '».'
                );
            }
            if ($propertyTitle === '' && $methodTitle === '') {
                throw new InvalidArgumentException('Выберите свойство или метод объекта либо очистите привязку.');
            }
        } else {
            $propertyTitle = '';
            $methodTitle = '';
        }
        $eventMode = (string)($input['event_mode'] ?? 'always');
        if (!in_array($eventMode, ['always', 'change'], true)) {
            throw new InvalidArgumentException('Режим события: «каждое сообщение» или «только изменение».');
        }
        $incoming = $this->rule($input['incoming'] ?? [], 'приёма');
        $outgoing = $this->rule($input['outgoing'] ?? [], 'отправки');
        $writeEnabled = !empty($input['write_enabled']);

        $this->repository->saveBinding($propertyId, [
            'linked_object' => $objectTitle,
            'linked_property' => $propertyTitle,
            'linked_method' => $methodTitle,
            'linked_object_id' => $objectId,
            'linked_property_id' => $linkedPropertyId,
            'enabled' => !empty($input['enabled']) ? 1 : 0,
            'write_enabled' => $writeEnabled ? 1 : 0,
            'event_mode' => $eventMode,
            'incoming' => $incoming === null ? null : ValueTransformer::json($incoming),
            'outgoing' => $outgoing === null ? null : ValueTransformer::json($outgoing),
        ]);

        $this->syncSubscription($subscriptions, $previous, $objectTitle, $propertyTitle, $objectId, $linkedPropertyId, $writeEnabled);

        return $this->repository->binding($propertyId) ?? [];
    }

    /**
     * Снимает подписку модуля со старого свойства и ставит на новое.
     */
    private function syncSubscription(
        ModuleSubscriptions $subscriptions,
        ?array $previous,
        string $objectTitle,
        string $propertyTitle,
        ?int $objectId,
        ?int $propertyId,
        bool $writeEnabled
    ): void {
        $shouldAttach = $writeEnabled && $objectId !== null && $propertyId !== null;
        if ($previous !== null && (int)$previous['write_enabled'] === 1) {
            $oldObjectId = (int)($previous['linked_object_id'] ?? 0);
            $oldPropertyId = (int)($previous['linked_property_id'] ?? 0);
            $oldObject = (string)($previous['linked_object'] ?? '');
            $oldProperty = (string)($previous['linked_property'] ?? '');
            $stale = !$shouldAttach
                || $oldObject !== $objectTitle
                || $oldProperty !== $propertyTitle;
            if ($stale) {
                if ($oldObjectId <= 0 || $oldPropertyId <= 0) {
                    $resolved = $this->properties->resolve($oldObject, $oldProperty);
                    $oldObjectId = (int)($resolved['object']['id'] ?? 0);
                    $oldPropertyId = (int)($resolved['property']['id'] ?? 0);
                }
                if ($oldObjectId > 0 && $oldPropertyId > 0
                    && $this->repository->otherBindingsFor($oldObject, $oldProperty, (int)$previous['id']) === 0) {
                    try {
                        $subscriptions->detach($oldObjectId, $oldPropertyId);
                    } catch (Throwable $error) {
                        $this->logger->warning('Zigbee: старая подписка свойства не снята', [
                            'error' => $error->getMessage(),
                        ]);
                    }
                }
            }
        }
        if ($shouldAttach) {
            $subscriptions->attach((int)$objectId, (int)$propertyId);
        }
    }

    /**
     * Снимает подписки всех write-привязок устройства перед удалением.
     */
    public function prepareDeviceDeletion(int $deviceId, ModuleSubscriptions $subscriptions): void
    {
        foreach ($this->repository->propertiesWithBindings($deviceId) as $property) {
            if (!$property['write_enabled']) {
                continue;
            }
            $binding = $property['binding'];
            $object = (string)($binding['linked_object'] ?? '');
            $linked = (string)($binding['linked_property'] ?? '');
            if ($object === '' || $linked === '') {
                continue;
            }
            $objectId = (int)($binding['linked_object_id'] ?? 0);
            $propertyId = (int)($binding['linked_property_id'] ?? 0);
            if ($objectId <= 0 || $propertyId <= 0) {
                $resolved = $this->properties->resolve($object, $linked);
                $objectId = (int)($resolved['object']['id'] ?? 0);
                $propertyId = (int)($resolved['property']['id'] ?? 0);
            }
            if ($objectId > 0 && $propertyId > 0
                && $this->repository->otherBindingsFor($object, $linked, (int)($binding['id'] ?? 0)) === 0) {
                try {
                    $subscriptions->detach($objectId, $propertyId);
                } catch (Throwable $error) {
                    $this->logger->warning('Zigbee: подписка свойства не снята', [
                        'error' => $error->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $device
     */
    public function deleteDevice(int $deviceId, ModuleSubscriptions $subscriptions): void
    {
        if ($this->repository->deviceById($deviceId) === null) {
            throw new InvalidArgumentException('Устройство не найдено.');
        }
        $this->prepareDeviceDeletion($deviceId, $subscriptions);
        $this->repository->deleteDevice($deviceId);
    }

    /**
     * @param array<string, mixed> $device
     */
    public function deleteBridge(int $bridgeId, ModuleSubscriptions $subscriptions): void
    {
        if ($this->repository->bridge($bridgeId) === null) {
            throw new InvalidArgumentException('Экземпляр не найден.');
        }
        foreach ($this->repository->devicesForBridge($bridgeId) as $device) {
            $this->prepareDeviceDeletion((int)$device['id'], $subscriptions);
        }
        $this->repository->deleteBridge($bridgeId);
    }

    /**
     * Проверяет правило преобразования и возвращает нормализованный массив.
     *
     * @return array<string, mixed>|null
     */
    private function rule(mixed $rule, string $label): ?array
    {
        if ($rule === null || $rule === [] || $rule === '') {
            return null;
        }
        if (!is_array($rule)) {
            throw new InvalidArgumentException('Правило ' . $label . ' должно быть объектом.');
        }
        try {
            return ValueTransformer::validate($rule);
        } catch (InvalidArgumentException $error) {
            throw new InvalidArgumentException('Правило ' . $label . ': ' . $error->getMessage(), 0, $error);
        }
    }

    /**
     * Проверяет преобразование без сохранения и отправки команды.
     *
     * @param array<string, mixed> $rule
     * @return array{input:mixed,output:mixed}
     */
    public function testConversion(int $propertyId, string $direction, mixed $value, array $rule): array
    {
        if (!in_array($direction, ['incoming', 'outgoing'], true)) {
            throw new InvalidArgumentException('Направление преобразования: incoming или outgoing.');
        }
        $property = $this->repository->propertyById($propertyId);
        if ($property === null) {
            throw new InvalidArgumentException('Свойство устройства не найдено.');
        }
        $expose = ValueTransformer::decode((string)($property['expose'] ?? '')) ?? [];
        $output = ValueTransformer::apply(
            $value,
            ValueTransformer::validate($rule),
            $expose,
            $direction === 'outgoing'
        );

        return ['input' => $value, 'output' => $output];
    }

    /**
     * Плоский список путей сообщения: вложенные объекты дают `color.x`, но
     * сам объект тоже сохраняется как JSON.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public static function flatten(array $values, string $prefix = ''): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $path = $prefix . $key;
            $result[$path] = $value;
            if (is_array($value) && !array_is_list($value)) {
                $result += self::flatten($value, $path . '.');
            }
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $features
     * @return array<string, array<string, mixed>>
     */
    public static function exposes(array $features, string $prefix = ''): array
    {
        $result = [];
        foreach ($features as $feature) {
            if (!is_array($feature)) {
                continue;
            }
            $property = (string)($feature['property'] ?? '');
            if ($property !== '') {
                $result[$prefix . $property] = $feature;
            }
            if (isset($feature['features']) && is_array($feature['features'])) {
                $result += self::exposes(
                    $feature['features'],
                    $property !== '' ? $prefix . $property . '.' : $prefix
                );
            }
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function enabledBridges(): array
    {
        return $this->repository->bridges(true);
    }
}
