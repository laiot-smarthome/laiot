<?php

declare(strict_types=1);

namespace MajorDoMo\Zigbee;

use InvalidArgumentException;
use MajorDoMo\Modules\ModuleDb;
use MajorDoMo\Modules\ModuleQuery;
use RuntimeException;

/**
 * Хранение Zigbee-данных через ModuleDb.
 *
 * Модуль видит только свои таблицы с префиксом `module_zigbee_`; JOIN и raw
 * SQL недоступны, поэтому связанные строки собираются несколькими запросами
 * и склейкой в PHP. Это осознанная граница SDK, а не временное ограничение.
 *
 * 2026-10-28 — репозиторий переписан с alpha `Database` на ModuleDb.
 */
final class Repository
{
    /** @var array<int, array<string, mixed>> */
    private array $bridgeCache = [];

    public function __construct(
        private readonly ModuleDb $moduleDb,
        private readonly Schema $schema
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bridges(bool $enabledOnly = false): array
    {
        $query = $this->table('bridges')->orderBy('id');
        if ($enabledOnly) {
            $query->where('enabled', '=', 1);
        }

        return $this->rows($query->get());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function bridge(int $id): ?array
    {
        if (array_key_exists($id, $this->bridgeCache)) {
            return $this->bridgeCache[$id];
        }
        $row = $this->row($this->table('bridges')->where('id', $id)->first());

        return $this->bridgeCache[$id] = $row === [] ? null : $row;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveBridge(array $input, int $id = 0): int
    {
        $title = trim((string)($input['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 190) {
            throw new InvalidArgumentException('Укажите название до 190 символов.');
        }
        $base = trim((string)($input['base_topic'] ?? ''), " /\t\r\n");
        if ($base === '' || strlen($base) > 190 || preg_match('/[+#\x00-\x1f]/', $base)) {
            throw new InvalidArgumentException('Укажите корневой топик без + и #, не длиннее 190 байт.');
        }
        if ($id > 0 && $this->bridge($id) === null) {
            throw new InvalidArgumentException('Экземпляр не найден.');
        }
        foreach ($this->bridges() as $bridge) {
            if ((int)$bridge['id'] === $id) {
                if ((string)$bridge['base_topic'] !== $base) {
                    throw new InvalidArgumentException(
                        'Корень существующего экземпляра менять нельзя: создайте новый экземпляр.'
                    );
                }

                continue;
            }
            $left = (string)$bridge['base_topic'] . '/';
            $right = $base . '/';
            if (str_starts_with($left, $right) || str_starts_with($right, $left)) {
                throw new InvalidArgumentException('Корневые топики экземпляров не должны пересекаться.');
            }
        }

        $row = [
            'title' => $title,
            'base_topic' => $base,
            'enabled' => !empty($input['enabled']) ? 1 : 0,
        ];
        if ($id > 0) {
            $this->table('bridges')->where('id', $id)->update($row);
            unset($this->bridgeCache[$id]);

            return $id;
        }
        $row += ['state' => 'unknown', 'last_seen' => null, 'info' => null];
        $newId = $this->table('bridges')->insertGetId($row);
        if ($newId <= 0) {
            throw new RuntimeException('Не удалось создать экземпляр Zigbee.');
        }

        return $newId;
    }

    public function saveBridgeState(int $id, string $state): void
    {
        $this->table('bridges')->where('id', $id)->update([
            'state' => $state,
            'last_seen' => date('Y-m-d H:i:s'),
        ]);
        unset($this->bridgeCache[$id]);
    }

    public function saveBridgeInfo(int $id, string $info): void
    {
        $this->table('bridges')->where('id', $id)->update([
            'info' => $info,
            'last_seen' => date('Y-m-d H:i:s'),
        ]);
        unset($this->bridgeCache[$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function deviceByIeee(int $bridgeId, string $ieee): ?array
    {
        $row = $this->row($this->table('devices')
            ->where('bridge_id', $bridgeId)
            ->where('ieee_address', strtolower(trim($ieee)))
            ->first());

        return $row === [] ? null : $row;
    }

    /**
     * Исходное значение пришло раньше, чем закончилось преобразование:
     * RAW_VALUE и UPDATED сохраняются отдельно от VALUE.
     */
    public function savePropertyValue(int $id, string $raw): void
    {
        $this->table('properties')->where('id', $id)->update([
            'raw_value' => $raw,
            'updated' => date('Y-m-d H:i:s'),
        ]);
    }

    public function saveConvertedValue(int $id, string $value): void
    {
        $this->table('properties')->where('id', $id)->update(['value' => $value]);
    }

    /**
     * Удаляет экземпляр вместе с устройствами, свойствами, привязками и
     * диагностическим журналом: чужого владельца у этих строк нет.
     */
    public function deleteBridge(int $id): void
    {
        $this->transaction(function () use ($id): void {
            foreach ($this->devicesForBridge($id) as $device) {
                $this->deleteDeviceRows((int)$device['id']);
            }
            $this->table('events')->where('bridge_id', $id)->delete();
            $this->table('bridges')->where('id', $id)->delete();
        });
        unset($this->bridgeCache[$id]);
    }

    /**
     * Находит или создаёт устройство бриджа.
     *
     * `FRIENDLY_NAME` и IEEE-адрес могут прийти в разном порядке: Zigbee2MQTT
     * сначала публикует сообщение устройства, затем `bridge/devices`. Поэтому
     * ранняя запись без IEEE при известном адресе объединяется с полной, а
     * привязки и комната переносятся на итоговую строку.
     *
     * @return array<string, mixed>
     */
    public function device(int $bridgeId, string $name, ?string $ieee = null): array
    {
        if ($name === '' || strlen($name) > 190 || preg_match('/[+#\x00-\x1f]/', $name)) {
            throw new InvalidArgumentException('Некорректное имя устройства.');
        }
        $ieee = $ieee !== null ? strtolower(trim($ieee)) : null;
        $row = $ieee !== null && $ieee !== ''
            ? $this->row($this->table('devices')
                ->where('bridge_id', $bridgeId)
                ->where('ieee_address', $ieee)
                ->first())
            : [];
        $named = $this->row($this->table('devices')
            ->where('bridge_id', $bridgeId)
            ->where('friendly_name', $name)
            ->first());

        if ($row !== [] && $named !== [] && (int)$row['id'] !== (int)$named['id']) {
            if ((string)($named['ieee_address'] ?? '') !== '') {
                throw new InvalidArgumentException('Имя устройства занято другим IEEE-адресом.');
            }
            $row['_replay'] = $this->mergeProvisional((int)$row['id'], (int)$named['id']);
        }
        if ($row === []) {
            $row = $named;
        }
        if ($row === []) {
            $id = $this->table('devices')->insertGetId([
                'bridge_id' => $bridgeId,
                'ieee_address' => $ieee !== '' ? $ieee : null,
                'friendly_name' => $name,
                'title' => '',
                'model' => '',
                'vendor' => '',
                'description' => '',
                'available' => 'unknown',
                'status' => 'discovered',
                'location_id' => 0,
                'last_seen' => null,
                'metadata' => null,
                'health_config' => null,
            ]);
            $row = $this->deviceById($id) ?? [];
        }

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function deviceById(int $id): ?array
    {
        $row = $this->row($this->table('devices')->where('id', $id)->first());

        return $row === [] ? null : $row;
    }

    /**
     * Устройства по списку id в порядке запроса.
     *
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    public function devicesByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $rows = [];
        foreach ($this->table('devices')->whereIn('id', $ids)->get() as $row) {
            $row = $this->row($row);
            $rows[(int)$row['id']] = $row;
        }
        $result = [];
        foreach ($ids as $id) {
            if (isset($rows[$id])) {
                $result[] = $rows[$id];
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function saveDevice(int $id, array $values): void
    {
        unset($values['id'], $values['_replay']);
        if ($values === []) {
            return;
        }
        $this->table('devices')->where('id', $id)->update($values);
    }

    /**
     * Переносит свойства, привязки и комнату с временной строки на основную.
     *
     * @return array<string, mixed> Значения для повторной доставки.
     */
    private function mergeProvisional(int $targetId, int $sourceId): array
    {
        $replay = [];
        $source = $this->deviceById($sourceId);
        $target = $this->deviceById($targetId);
        if ($source === null || $target === null) {
            return $replay;
        }
        if ((string)($source['health_config'] ?? '') !== '') {
            if ((string)($target['health_config'] ?? '') !== ''
                && $source['health_config'] !== $target['health_config']) {
                throw new InvalidArgumentException('Разные настройки мониторинга у объединяемых устройств.');
            }
            $this->saveDevice($targetId, ['health_config' => $source['health_config']]);
        }
        $sourceAdded = (string)$source['status'] === 'added';
        $targetAdded = (string)$target['status'] === 'added';
        if ($sourceAdded && !$targetAdded) {
            $this->saveDevice($targetId, [
                'status' => 'added',
                'location_id' => (int)$source['location_id'],
                'title' => (string)$source['title'],
            ]);
        } elseif ($sourceAdded && $targetAdded
            && (int)$source['location_id'] > 0 && (int)$target['location_id'] > 0
            && (int)$source['location_id'] !== (int)$target['location_id']) {
            throw new InvalidArgumentException('Разные комнаты у объединяемых устройств.');
        } elseif ($sourceAdded && (int)$source['location_id'] > 0 && (int)$target['location_id'] === 0) {
            $this->saveDevice($targetId, ['location_id' => (int)$source['location_id']]);
        }

        foreach ($this->propertiesForDevice($sourceId) as $property) {
            $existing = $this->row($this->table('properties')
                ->where('device_id', $targetId)
                ->where('path', (string)$property['path'])
                ->first());
            if ($existing === []) {
                $this->table('properties')->where('id', (int)$property['id'])->update(['device_id' => $targetId]);
                continue;
            }
            $from = $this->binding((int)$property['id']);
            $to = $this->binding((int)$existing['id']);
            if ($from !== null && $to !== null) {
                throw new InvalidArgumentException('Конфликт привязок при переименовании: ' . $property['path']);
            }
            if ($from !== null) {
                $this->table('bindings')->where('id', (int)$from['id'])->update(['property_id' => (int)$existing['id']]);
            }
            if ($property['raw_value'] !== null
                && ($existing['updated'] === null || $property['updated'] >= $existing['updated'])) {
                $this->table('properties')->where('id', (int)$existing['id'])->update([
                    'raw_value' => $property['raw_value'],
                    'value' => $property['value'],
                    'updated' => $property['updated'],
                ]);
                $replay[(string)$property['path']] = ValueTransformer::decode((string)$property['raw_value']);
            }
            $this->table('properties')->where('id', (int)$property['id'])->delete();
        }
        $this->table('events')->where('device_id', $sourceId)->update(['device_id' => $targetId]);
        $this->table('devices')->where('id', $sourceId)->delete();
        $this->table('health_state')->where('subject', 'like', 'device:' . $sourceId . ':%')->delete();

        return $replay;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function devicesForBridge(int $bridgeId, string $status = ''): array
    {
        $query = $this->table('devices')->where('bridge_id', $bridgeId)->orderBy('friendly_name');
        if ($status !== '') {
            $query->where('status', $status);
        }

        return $this->rows($query->get());
    }

    /**
     * Список устройств для страницы модуля.
     *
     * @param array{q?:string,status?:string,bridge_id?:int,location_id?:int,available?:string,page?:int,per_page?:int,sort?:string} $filters
     * @return array{rows:list<array<string, mixed>>,total:int,page:int,pages:int,per_page:int}
     */
    public function searchDevices(array $filters): array
    {
        $perPage = max(5, min(100, (int)($filters['per_page'] ?? 24)));
        $page = max(1, (int)($filters['page'] ?? 1));
        $base = $this->table('devices');
        $this->applyDeviceFilters($base, $filters);
        $total = $base->count();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);

        $query = $this->table('devices');
        $this->applyDeviceFilters($query, $filters);
        $sort = (string)($filters['sort'] ?? 'name');
        if ($sort === 'seen') {
            $query->orderByDesc('last_seen')->orderBy('friendly_name');
        } elseif ($sort === 'model') {
            $query->orderBy('model')->orderBy('friendly_name');
        } else {
            $query->orderBy('friendly_name');
        }
        $rows = $this->rows($query->offset(($page - 1) * $perPage)->limit($perPage)->get());

        $bridges = [];
        foreach ($this->bridges() as $bridge) {
            $bridges[(int)$bridge['id']] = (string)$bridge['title'];
        }
        foreach ($rows as &$row) {
            $row['bridge_title'] = $bridges[(int)$row['bridge_id']] ?? '—';
        }
        unset($row);

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyDeviceFilters(ModuleQuery $query, array $filters): void
    {
        $status = (string)($filters['status'] ?? '');
        if (in_array($status, ['added', 'discovered'], true)) {
            $query->where('status', $status);
        }
        if ((int)($filters['bridge_id'] ?? 0) > 0) {
            $query->where('bridge_id', (int)$filters['bridge_id']);
        }
        if ((int)($filters['location_id'] ?? 0) > 0) {
            $query->where('location_id', (int)$filters['location_id']);
        }
        $available = (string)($filters['available'] ?? '');
        if (in_array($available, ['online', 'offline'], true)) {
            $query->where('available', $available);
        }
        $search = trim((string)($filters['q'] ?? ''));
        if ($search !== '') {
            $needle = '%' . $search . '%';
            $query->whereGroup(static function (ModuleQuery $inner) use ($needle): void {
                $inner->where('friendly_name', 'like', $needle)
                    ->orWhere('title', 'like', $needle)
                    ->orWhere('model', 'like', $needle)
                    ->orWhere('ieee_address', 'like', $needle);
            });
        }
    }

    /**
     * @return array{bridges:int,total:int,added:int,discovered:int,online:int,offline:int}
     */
    public function counts(): array
    {
        return [
            'bridges' => $this->table('bridges')->count(),
            'total' => $this->table('devices')->count(),
            'added' => $this->table('devices')->where('status', 'added')->count(),
            'discovered' => $this->table('devices')->where('status', 'discovered')->count(),
            'online' => $this->table('devices')->where('available', 'online')->count(),
            'offline' => $this->table('devices')->where('available', 'offline')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function property(int $deviceId, string $path, ?array $expose = null): array
    {
        if ($path === '' || strlen($path) > 190) {
            throw new InvalidArgumentException('Путь свойства слишком длинный или пустой.');
        }
        $row = $this->row($this->table('properties')
            ->where('device_id', $deviceId)
            ->where('path', $path)
            ->first());
        if ($row === []) {
            $id = $this->table('properties')->insertGetId([
                'device_id' => $deviceId,
                'path' => $path,
                'label' => '',
                'expose' => null,
                'raw_value' => null,
                'value' => null,
                'updated' => null,
            ]);
            $row = $this->propertyById($id) ?? [];
        }
        if ($expose !== null && $row !== []) {
            $encoded = ValueTransformer::json($expose);
            $label = mb_substr((string)($expose['label'] ?? $path), 0, 190);
            if ($row['expose'] !== $encoded || $row['label'] !== $label) {
                $this->table('properties')->where('id', (int)$row['id'])->update([
                    'expose' => $encoded,
                    'label' => $label,
                ]);
                $row['expose'] = $encoded;
                $row['label'] = $label;
            }
        }

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function propertyById(int $id): ?array
    {
        $row = $this->row($this->table('properties')->where('id', $id)->first());

        return $row === [] ? null : $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function propertiesForDevice(int $deviceId): array
    {
        return $this->rows($this->table('properties')->where('device_id', $deviceId)->orderBy('path')->get());
    }

    /**
     * Свойства устройства вместе с привязкой: два запроса без JOIN.
     *
     * @return list<array<string, mixed>>
     */
    public function propertiesWithBindings(int $deviceId): array
    {
        $properties = $this->propertiesForDevice($deviceId);
        $ids = array_map(static fn (array $row): int => (int)$row['id'], $properties);
        $bindings = [];
        if ($ids !== []) {
            foreach ($this->table('bindings')->whereIn('property_id', $ids)->get() as $binding) {
                $row = $this->row($binding);
                $bindings[(int)$row['property_id']] = $row;
            }
        }
        foreach ($properties as &$property) {
            $binding = $bindings[(int)$property['id']] ?? null;
            $property['binding'] = $binding;
            $property['binding_id'] = $binding['id'] ?? null;
            $property['linked_object'] = $binding['linked_object'] ?? '';
            $property['linked_property'] = $binding['linked_property'] ?? '';
            $property['linked_method'] = $binding['linked_method'] ?? '';
            $property['write_enabled'] = (bool)($binding['write_enabled'] ?? false);
            $property['binding_enabled'] = (bool)($binding['enabled'] ?? false);
            $property['last_error'] = $binding['last_error'] ?? '';
        }
        unset($property);

        return $properties;
    }

    /**
     * Исходные значения свойств устройства: `path => raw_value`.
     *
     * @return array<string, string|null>
     */
    public function propertyValues(int $deviceId): array
    {
        $values = [];
        foreach ($this->propertiesForDevice($deviceId) as $property) {
            $values[(string)$property['path']] = $property['raw_value'] !== null
                ? (string)$property['raw_value']
                : null;
        }

        return $values;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function binding(int $propertyId): ?array
    {
        $row = $this->row($this->table('bindings')->where('property_id', $propertyId)->first());

        return $row === [] ? null : $row;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function saveBinding(int $propertyId, array $values): int
    {
        $existing = $this->binding($propertyId);
        unset($values['id'], $values['property_id']);
        if ($existing === null) {
            $values['property_id'] = $propertyId;
            $values += [
                'linked_object' => '',
                'linked_property' => '',
                'linked_method' => '',
                'linked_object_id' => null,
                'linked_property_id' => null,
                'enabled' => 1,
                'write_enabled' => 0,
                'event_mode' => 'always',
                'incoming' => null,
                'outgoing' => null,
                'last_delivered' => null,
                'last_error' => null,
            ];

            return $this->table('bindings')->insertGetId($values);
        }
        if ($values !== []) {
            $this->table('bindings')->where('property_id', $propertyId)->update($values);
        }

        return (int)$existing['id'];
    }

    public function deleteBinding(int $propertyId): void
    {
        $this->table('bindings')->where('property_id', $propertyId)->delete();
    }

    /**
     * Привязки, которые отправляют изменение свойства в устройство.
     *
     * @return list<array<string, mixed>>
     */
    public function writeBindings(string $objectTitle, string $propertyTitle): array
    {
        $bindings = $this->rows($this->table('bindings')
            ->where('linked_object', $objectTitle)
            ->where('linked_property', $propertyTitle)
            ->where('enabled', 1)
            ->where('write_enabled', 1)
            ->get());
        $result = [];
        foreach ($bindings as $binding) {
            $property = $this->propertyById((int)$binding['property_id']);
            if ($property === null) {
                continue;
            }
            $device = $this->deviceById((int)$property['device_id']);
            if ($device === null) {
                continue;
            }
            $bridge = $this->bridge((int)$device['bridge_id']);
            if ($bridge === null) {
                continue;
            }
            $binding['property'] = $property;
            $binding['device'] = $device;
            $binding['bridge'] = $bridge;
            $result[] = $binding;
        }

        return $result;
    }

    /**
     * Количество других привязок к тому же свойству (для снятия подписки).
     */
    public function otherBindingsFor(string $objectTitle, string $propertyTitle, int $exceptId): int
    {
        return $this->table('bindings')
            ->where('linked_object', $objectTitle)
            ->where('linked_property', $propertyTitle)
            ->where('id', '<>', $exceptId)
            ->count();
    }

    /**
     * @param array{q?:string,bridge_id?:int,device_id?:int,level?:string,page?:int,per_page?:int} $filters
     * @return array{rows:list<array<string, mixed>>,total:int,page:int,pages:int,per_page:int}
     */
    public function events(array $filters): array
    {
        $perPage = max(10, min(100, (int)($filters['per_page'] ?? 50)));
        $page = max(1, (int)($filters['page'] ?? 1));
        $build = function () use ($filters): ModuleQuery {
            $query = $this->table('events');
            if ((int)($filters['bridge_id'] ?? 0) > 0) {
                $query->where('bridge_id', (int)$filters['bridge_id']);
            }
            if ((int)($filters['device_id'] ?? 0) > 0) {
                $query->where('device_id', (int)$filters['device_id']);
            }
            $level = (string)($filters['level'] ?? '');
            if (in_array($level, ['info', 'warning', 'error'], true)) {
                $query->where('level', $level);
            }
            $search = trim((string)($filters['q'] ?? ''));
            if ($search !== '') {
                $needle = '%' . $search . '%';
                $query->whereGroup(static function (ModuleQuery $inner) use ($needle): void {
                    $inner->where('topic', 'like', $needle)
                        ->orWhere('message', 'like', $needle);
                });
            }

            return $query;
        };
        $total = $build()->count();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $rows = $this->rows(
            $build()->orderByDesc('id')->offset(($page - 1) * $perPage)->limit($perPage)->get()
        );
        $bridges = [];
        foreach ($this->bridges() as $bridge) {
            $bridges[(int)$bridge['id']] = (string)$bridge['title'];
        }
        $devices = [];
        foreach ($this->table('devices')->get() as $device) {
            $row = $this->row($device);
            $devices[(int)$row['id']] = (string)($row['title'] !== '' ? $row['title'] : $row['friendly_name']);
        }
        foreach ($rows as &$row) {
            $row['bridge_title'] = $bridges[(int)$row['bridge_id']] ?? '—';
            $row['device_title'] = (int)($row['device_id'] ?? 0) > 0
                ? ($devices[(int)$row['device_id']] ?? '—')
                : '';
        }
        unset($row);

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    public function log(int $bridgeId, ?int $deviceId, string $topic, string $message, string $level = 'info'): void
    {
        $this->table('events')->insert([
            'bridge_id' => $bridgeId,
            'device_id' => $deviceId !== null && $deviceId > 0 ? $deviceId : null,
            'topic' => mb_substr($topic, 0, 512),
            'message' => mb_substr($message, 0, 12000),
            'level' => in_array($level, ['info', 'warning', 'error'], true) ? $level : 'info',
            'added' => date('Y-m-d H:i:s'),
        ]);
        // Журнал ограничен последними 1000 записями на экземпляр: это
        // диагностика, а не архив, поэтому рост таблицы не нужен.
        $cut = $this->table('events')
            ->where('bridge_id', $bridgeId)
            ->orderByDesc('id')
            ->limit(1)
            ->offset(999)
            ->value('id');
        if ($cut !== null) {
            $this->table('events')->where('bridge_id', $bridgeId)->where('id', '<', (int)$cut)->delete();
        }
    }

    public function clearEvents(?int $bridgeId = null): int
    {
        $query = $this->table('events');
        if ($bridgeId !== null && $bridgeId > 0) {
            $query->where('bridge_id', $bridgeId);
        }

        return $query->delete();
    }

    /**
     * @param array<string, mixed>|null $device
     * @return array<string, mixed>
     */
    public function healthSettings(?array $device = null): array
    {
        $row = $this->table('health_settings')->where('id', 1)->first();
        $global = HealthSettings::validate(ValueTransformer::decode($this->row($row)['config'] ?? null) ?? []);
        if ($device === null || trim((string)($device['health_config'] ?? '')) === '') {
            return $global;
        }

        return HealthSettings::validate(ValueTransformer::decode((string)$device['health_config']) ?? []);
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function saveHealthSettings(array $settings, int $deviceId = 0, bool $inherit = false): void
    {
        $config = ValueTransformer::json(HealthSettings::validate($settings));
        if ($deviceId > 0) {
            if ($this->deviceById($deviceId) === null) {
                throw new InvalidArgumentException('Устройство не найдено.');
            }
            $this->saveDevice($deviceId, ['health_config' => $inherit ? null : $config]);

            return;
        }
        if ($this->table('health_settings')->where('id', 1)->exists()) {
            $this->table('health_settings')->where('id', 1)->update(['config' => $config]);
        } else {
            $this->table('health_settings')->insert(['id' => 1, 'config' => $config]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function healthState(string $subject): array
    {
        $row = $this->row($this->table('health_state')->where('subject', $subject)->first());
        if ($row === []) {
            return [];
        }
        $data = ValueTransformer::decode((string)$row['data']);

        return array_replace(is_array($data) ? $data : [], ['_id' => (int)$row['id']]);
    }

    /**
     * @param array<string, mixed> $state
     */
    public function saveHealthState(string $subject, array $state): void
    {
        $id = (int)($state['_id'] ?? 0);
        unset($state['_id']);
        $data = ValueTransformer::json($state);
        if ($id > 0) {
            $this->table('health_state')->where('id', $id)->update(['subject' => $subject, 'data' => $data]);

            return;
        }
        $existing = $this->table('health_state')->where('subject', $subject)->first();
        if ($existing !== null) {
            $this->table('health_state')->where('subject', $subject)->update(['data' => $data]);

            return;
        }
        $this->table('health_state')->insert(['subject' => $subject, 'data' => $data]);
    }

    /**
     * Состояние мониторинга для списка устройств.
     *
     * @param list<int> $deviceIds
     * @return array<int, array<string, mixed>>
     */
    public function healthStates(array $deviceIds): array
    {
        $result = [];
        if ($deviceIds === []) {
            return $result;
        }
        $subjects = [];
        foreach ($deviceIds as $id) {
            $subjects[] = 'device:' . $id . ':availability';
            $subjects[] = 'device:' . $id . ':battery';
        }
        foreach ($this->table('health_state')->whereIn('subject', $subjects)->get() as $row) {
            $row = $this->row($row);
            if (preg_match('/^device:(\d+):(availability|battery)$/D', (string)$row['subject'], $match) !== 1) {
                continue;
            }
            $result[(int)$match[1]][$match[2]] = ValueTransformer::decode((string)$row['data']);
        }

        return $result;
    }

    /**
     * Удаляет устройство и его привязки. Объектные привязки модуля при этом
     * снимает сервис, а не репозиторий.
     */
    public function deleteDevice(int $id): void
    {
        $this->transaction(fn () => $this->deleteDeviceRows($id));
    }

    private function deleteDeviceRows(int $deviceId): void
    {
        $propertyIds = array_map(
            static fn (array $row): int => (int)$row['id'],
            $this->propertiesForDevice($deviceId)
        );
        if ($propertyIds !== []) {
            $this->table('bindings')->whereIn('property_id', $propertyIds)->delete();
            $this->table('properties')->whereIn('id', $propertyIds)->delete();
        }
        $this->table('events')->where('device_id', $deviceId)->delete();
        $this->table('health_state')->where('subject', 'like', 'device:' . $deviceId . ':%')->delete();
        $this->table('devices')->where('id', $deviceId)->delete();
    }

    /**
     * Граница транзакции SDK: одиночные SELECT снаружи её не открывают.
     *
     * @template T
     * @param callable():T $work
     * @return T
     */
    public function transaction(callable $work): mixed
    {
        return $this->moduleDb->transaction($work);
    }

    private function table(string $logical): ModuleQuery
    {
        $this->schema->ensure();

        return $this->moduleDb->table($logical);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(mixed $row): array
    {
        if (is_object($row)) {
            return (array)$row;
        }

        return is_array($row) ? $row : [];
    }

    /**
     * @param iterable<mixed> $rows
     * @return list<array<string, mixed>>
     */
    private function rows(iterable $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->row($row);
        }

        return $result;
    }
}
