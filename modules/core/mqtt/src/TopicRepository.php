<?php

declare(strict_types=1);

namespace MajorDoMo\Mqtt;

use MajorDoMo\Cache\CacheStore;
use MajorDoMo\Modules\ModuleDb;
use MajorDoMo\Modules\ModuleQuery;

/**
 * Топики MQTT: настройка привязки, последнее значение и журнал.
 *
 * Одна таблица `module_mqtt_topics` хранит и правило, и состояние — как в
 * alpha. Обработчик получает снимок привязок в память (BindingIndex), а
 * репозиторий обслуживает страницы, импорт и запись значений.
 *
 * 2026-10-27 — перенос модели alpha `mqtt`/`mqtt_history` в ModuleDb.
 */
final class TopicRepository
{
    public const DIRECTION_IN = 0;

    public const DIRECTION_OUT = 1;

    private const PER_PAGE = 20;

    private const GROUPS_LIMIT = 300;

    public function __construct(
        private readonly Schema $schema,
        private readonly ModuleDb $moduleDb,
        private readonly CacheStore $cache
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $this->schema->ensure();
        $row = $this->moduleDb->table('topics')->where('id', $id)->first();

        return $row === null ? null : self::row($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByPath(string $path): ?array
    {
        if ($path === '') {
            return null;
        }
        $this->schema->ensure();
        $row = $this->moduleDb->table('topics')->where('path', $path)->first();

        return $row === null ? null : self::row($row);
    }

    /**
     * Снимок для индекса привязок и для страницы: одна выборка без N+1.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $this->schema->ensure();
        $rows = $this->moduleDb->table('topics')->orderBy('path')->get();
        $result = [];
        foreach ($rows as $row) {
            $result[] = self::row($row);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): int
    {
        $this->schema->ensure();
        $path = trim((string)($data['path'] ?? ''));
        $pathKey = self::pathKey($path);
        $now = date('Y-m-d H:i:s');
        $values = [
            'path' => $path,
            'path_key' => $pathKey,
            'title' => trim((string)($data['title'] ?? '')),
            'path_write' => trim((string)($data['path_write'] ?? '')),
            'linked_object' => trim((string)($data['linked_object'] ?? '')),
            'linked_property' => trim((string)($data['linked_property'] ?? '')),
            'linked_method' => trim((string)($data['linked_method'] ?? '')),
            'linked_object_id' => ($data['linked_object_id'] ?? null) !== null ? (int)$data['linked_object_id'] : null,
            'linked_property_id' => ($data['linked_property_id'] ?? null) !== null ? (int)$data['linked_property_id'] : null,
            'location_id' => ($data['location_id'] ?? null) !== null ? (int)$data['location_id'] : null,
            'logging' => !empty($data['logging']) ? 1 : 0,
            'only_new_value' => !empty($data['only_new_value']) ? 1 : 0,
            'readonly' => !empty($data['readonly']) ? 1 : 0,
            'replace_list' => trim((string)($data['replace_list'] ?? '')),
            'qos' => max(0, min(2, (int)($data['qos'] ?? 0))),
            'retain' => !empty($data['retain']) ? 1 : 0,
            'updated_at' => $now,
        ];
        if (array_key_exists('value', $data)) {
            $values['value'] = (string)($data['value'] ?? '');
            $values['updated'] = (string)($data['updated'] ?? $now);
        }

        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $this->moduleDb->table('topics')->where('id', $id)->update($values);
        } else {
            $values['created_at'] = $now;
            $id = $this->moduleDb->table('topics')->insertGetId($values);
        }

        $this->forgetCaches();

        return $id;
    }

    /**
     * Регистрирует новый топик, обнаруженный в потоке брокера.
     *
     * Такой топик не меняет привязки, поэтому версию индекса не трогаем:
     * иначе пачка новых датчиков вызывала бы полное перечитывание снимка
     * после каждой вставки.
     */
    public function discover(string $path, string $value): int
    {
        $this->schema->ensure();
        $now = date('Y-m-d H:i:s');
        $id = $this->moduleDb->table('topics')->insertGetId([
            'path' => $path,
            'path_key' => self::pathKey($path),
            'title' => $path,
            'value' => mb_strcut($value, 0, 65535, 'UTF-8'),
            'updated' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->cache->forget('mqtt.stats');

        return $id;
    }

    public function updateValue(int $id, string $value, ?string $updated = null): void
    {
        if ($id <= 0) {
            return;
        }
        $this->moduleDb->table('topics')->where('id', $id)->update([
            'value' => mb_strcut($value, 0, 65535, 'UTF-8'),
            'updated' => $updated ?? date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function delete(int $id): void
    {
        if ($id <= 0) {
            return;
        }
        $this->moduleDb->table('history')->where('topic_id', $id)->delete();
        $this->moduleDb->table('topics')->where('id', $id)->delete();
        $this->forgetCaches();
    }

    /**
     * @param list<int> $ids
     */
    public function deleteMany(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        $topics = 0;
        foreach (array_chunk($ids, 400) as $chunk) {
            $this->moduleDb->table('history')->whereIn('topic_id', $chunk)->delete();
            $topics += $this->moduleDb->table('topics')->whereIn('id', $chunk)->delete();
        }
        $this->forgetCaches();

        return $topics;
    }

    /**
     * Удаляет топики без привязки (объект, свойство и метод пустые).
     */
    public function clearTrash(): int
    {
        $rows = $this->moduleDb->table('topics')
            ->where('linked_object', '=', '')
            ->where('linked_property', '=', '')
            ->where('linked_method', '=', '')
            ->limit(5000)
            ->get();
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int)$row->id;
        }

        return $this->deleteMany($ids);
    }

    public function countBindingsFor(string $object, int $propertyId, int $exceptId = 0): int
    {
        if ($object === '' || $propertyId <= 0) {
            return 0;
        }
        $query = $this->moduleDb->table('topics')
            ->where('linked_object', '=', $object)
            ->where('linked_property_id', '=', $propertyId);
        if ($exceptId > 0) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(int $topicId, int $limit = 20): array
    {
        if ($topicId <= 0) {
            return [];
        }
        $rows = $this->moduleDb->table('history')
            ->where('topic_id', $topicId)
            ->orderByDesc('id')
            ->limit(max(1, min(200, $limit)))
            ->get();
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => (int)$row->id,
                'topic_id' => (int)$row->topic_id,
                'direction' => (int)$row->direction,
                'topic' => (string)$row->topic,
                'value' => (string)($row->value ?? ''),
                'payload' => (string)($row->payload ?? ''),
                'linked_data' => (string)$row->linked_data,
                'created_at' => (string)$row->created_at,
            ];
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function addHistory(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }
        $this->moduleDb->table('history')->insert($rows);

        return count($rows);
    }

    public function pruneHistory(int $days): int
    {
        $days = max(1, min(3650, $days));
        $cutoff = date('Y-m-d H:i:s', time() - $days * 86400);

        return $this->moduleDb->table('history')->where('created_at', '<', $cutoff)->delete();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,per_page:int}
     */
    public function search(array $filters): array
    {
        $this->schema->ensure();
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(10, min(100, (int)($filters['per_page'] ?? self::PER_PAGE)));

        $total = $this->filtered($filters)->count();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $rows = $this->filtered($filters)
            ->orderBy(...$this->order((string)($filters['sort'] ?? 'updated')))
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = self::row($row);
        }

        return [
            'rows' => $result,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array{total:int,linked:int,unlinked:int,active:int,devices:int,last_update:string}
     */
    public function stats(): array
    {
        $cached = $this->cache->get('mqtt.stats');
        if (is_array($cached)) {
            return $cached;
        }

        $this->schema->ensure();
        $total = $this->moduleDb->table('topics')->count();
        $linked = $this->moduleDb->table('topics')->where('linked_object', '<>', '')->count();
        $active = $this->moduleDb->table('topics')
            ->where('updated', '>=', date('Y-m-d H:i:s', time() - 86400))
            ->count();
        $last = $this->moduleDb->table('topics')->orderByDesc('updated')->value('updated');
        $devices = [];
        foreach ($this->moduleDb->table('topics')->pluck('linked_object') as $object) {
            $object = trim((string)$object);
            if ($object !== '') {
                $devices[$object] = true;
            }
        }

        $stats = [
            'total' => $total,
            'linked' => $linked,
            'unlinked' => max(0, $total - $linked),
            'active' => $active,
            'devices' => count($devices),
            'last_update' => $last === null ? '' : (string)$last,
        ];
        $this->cache->put('mqtt.stats', $stats, 5);

        return $stats;
    }

    /**
     * Группы для боковой панели: устройства или первые сегменты путей.
     *
     * @return array{groups:list<array{key:string,title:string,count:int}>,limited:bool}
     */
    public function groups(string $mode): array
    {
        $mode = $mode === 'path' ? 'path' : 'device';
        $version = (string)($this->cache->get('mqtt.bindings_version') ?? '0');
        $cacheKey = 'mqtt.groups.' . $mode . '.' . $version;
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $this->schema->ensure();
        $counts = [];
        if ($mode === 'device') {
            foreach ($this->moduleDb->table('topics')->pluck('linked_object') as $object) {
                $object = trim((string)$object);
                if ($object !== '') {
                    $counts[$object] = ($counts[$object] ?? 0) + 1;
                }
            }
        } else {
            foreach ($this->moduleDb->table('topics')->pluck('path_key') as $path) {
                $parts = explode('/', trim((string)$path, '/'));
                $first = $parts[0];
                if ($first !== '') {
                    $counts[$first] = ($counts[$first] ?? 0) + 1;
                }
            }
        }

        uksort($counts, static fn (string $left, string $right): int => ($counts[$right] <=> $counts[$left]) ?: strnatcasecmp($left, $right));
        $limited = count($counts) > self::GROUPS_LIMIT;
        if ($limited) {
            $counts = array_slice($counts, 0, self::GROUPS_LIMIT, true);
        }

        $groups = [];
        foreach ($counts as $key => $count) {
            $groups[] = ['key' => (string)$key, 'title' => (string)$key, 'count' => (int)$count];
        }

        $result = ['groups' => $groups, 'limited' => $limited];
        $this->cache->put($cacheKey, $result, 30);

        return $result;
    }

    /**
     * Дочерние топики выбранного пути.
     *
     * @return list<array<string, mixed>>
     */
    public function children(string $path, int $limit = 50): array
    {
        $key = self::pathKey($path);
        if ($key === '') {
            return [];
        }
        $rows = $this->moduleDb->table('topics')
            ->where('path_key', 'like', $key . '/%')
            ->orderBy('path')
            ->limit(max(1, min(200, $limit)))
            ->get();
        $result = [];
        foreach ($rows as $row) {
            $result[] = self::row($row);
        }

        return $result;
    }

    /**
     * Нормализованный путь без ведущих и завершающих слешей.
     */
    public static function pathKey(string $path): string
    {
        return trim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(object $row): array
    {
        return [
            'id' => (int)$row->id,
            'path' => (string)$row->path,
            'path_key' => (string)$row->path_key,
            'title' => (string)$row->title,
            'path_write' => (string)$row->path_write,
            'linked_object' => (string)$row->linked_object,
            'linked_property' => (string)$row->linked_property,
            'linked_method' => (string)$row->linked_method,
            'linked_object_id' => $row->linked_object_id === null ? null : (int)$row->linked_object_id,
            'linked_property_id' => $row->linked_property_id === null ? null : (int)$row->linked_property_id,
            'location_id' => $row->location_id === null ? null : (int)$row->location_id,
            'logging' => (bool)$row->logging,
            'only_new_value' => (bool)$row->only_new_value,
            'readonly' => (bool)$row->readonly,
            'replace_list' => (string)$row->replace_list,
            'qos' => (int)$row->qos,
            'retain' => (bool)$row->retain,
            'value' => (string)($row->value ?? ''),
            'updated' => $row->updated === null ? '' : (string)$row->updated,
            'created_at' => (string)($row->created_at ?? ''),
            'updated_at' => (string)($row->updated_at ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function filtered(array $filters): ModuleQuery
    {
        $query = $this->moduleDb->table('topics');
        $group = trim((string)($filters['group'] ?? ''));
        $groupBy = (string)($filters['group_by'] ?? 'device') === 'path' ? 'path' : 'device';

        if ($group === '__unlinked__') {
            $query->where('linked_object', '=', '');
        } elseif ($group !== '') {
            if ($groupBy === 'path') {
                $key = self::pathKey($group);
                $query->whereGroup(static function (ModuleQuery $inner) use ($key): void {
                    $inner->where('path_key', '=', $key)
                        ->orWhere('path_key', 'like', $key . '/%');
                });
            } else {
                $query->where('linked_object', '=', $group);
            }
        }

        $linked = (string)($filters['linked'] ?? '');
        if ($linked === '1') {
            $query->where('linked_object', '<>', '');
        } elseif ($linked === '0') {
            $query->where('linked_object', '=', '');
        }

        $location = (int)($filters['location_id'] ?? 0);
        if ($location > 0) {
            $query->where('location_id', '=', $location);
        }

        $prefix = self::pathKey((string)($filters['path_prefix'] ?? ''));
        if ($prefix !== '') {
            $query->whereGroup(static function (ModuleQuery $inner) use ($prefix): void {
                $inner->where('path_key', '=', $prefix)
                    ->orWhere('path_key', 'like', $prefix . '/%');
            });
        }

        $search = trim((string)($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->whereGroup(static function (ModuleQuery $inner) use ($like): void {
                $inner->where('path', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('value', 'like', $like)
                    ->orWhere('linked_object', 'like', $like)
                    ->orWhere('linked_property', 'like', $like)
                    ->orWhere('linked_method', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function order(string $sort): array
    {
        return match ($sort) {
            'path' => ['path', 'asc'],
            'title' => ['title', 'asc'],
            'value' => ['value', 'asc'],
            default => ['updated', 'desc'],
        };
    }

    private function forgetCaches(): void
    {
        $this->cache->forget('mqtt.stats');
        $this->cache->increment('mqtt.bindings_version');
    }
}
