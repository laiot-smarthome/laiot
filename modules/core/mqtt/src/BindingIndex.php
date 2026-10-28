<?php

declare(strict_types=1);

namespace MajorDoMo\Mqtt;

use MajorDoMo\Cache\CacheStore;

/**
 * Индекс привязок MQTT в памяти обработчика.
 *
 * На каждое входящее сообщение нельзя ходить в MySQL за привязками: индекс
 * строится одним снимком, а версия в CacheStore сообщает, что редактор
 * изменил правило. Перечитывание — не чаще 0.5 с; полный сброс раз в 30 с
 * ловит правки, сделанные другим процессом без увеличения версии.
 *
 * Хранятся только привязанные топики и множество известных путей, поэтому
 * индекс на большом дереве остаётся компактным.
 *
 * 2026-10-27 — индекс привязок вместо SQL на каждое сообщение (alpha).
 */
final class BindingIndex
{
    /** @var array<string, array<string, mixed>> */
    private array $bindings = [];

    /**
     * Настроенные, но не привязанные топики: replace_list, logging,
     * only_new_value и собственный path_write. Без них настройки не-привязанного
     * топика терялись бы при чтении из индекса.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $records = [];

    /** @var array<string, int> */
    private array $writePaths = [];

    /** @var array<string, int> */
    private array $linkedPrefixes = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $byProperty = [];

    /** @var array<string, int> */
    private array $known = [];

    private string $version = '';

    private float $loadedAt = 0.0;

    private float $checkedAt = 0.0;

    public function __construct(
        private readonly TopicRepository $topics,
        private readonly CacheStore $cache
    ) {
    }

    public function ready(): bool
    {
        return $this->loadedAt > 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function record(string $path): array
    {
        return $this->bindings[$path] ?? $this->records[$path] ?? [];
    }

    /**
     * Регистрирует только что обнаруженный топик без полного перечитывания
     * снимка: следующий пакет уже знает его id.
     */
    public function rememberKnown(string $path, int $id): void
    {
        if ($path !== '' && $id > 0) {
            $this->known[$path] = $id;
        }
    }

    public function isKnown(string $path): bool
    {
        return isset($this->known[$path]);
    }

    public function knownId(string $path): int
    {
        return $this->known[$path] ?? 0;
    }

    public function isWritePath(string $path): bool
    {
        return isset($this->writePaths[$path]);
    }

    public function isLinked(string $path): bool
    {
        return isset($this->bindings[$path]);
    }

    public function hasLinkedPrefix(string $path): bool
    {
        return $path !== '' && isset($this->linkedPrefixes[$path]);
    }

    public function isLinkedOrPrefix(string $path): bool
    {
        return $this->isLinked($path) || $this->hasLinkedPrefix($path);
    }

    /**
     * Привязки конкретного свойства: по ним публикуется изменение.
     *
     * @return list<array<string, mixed>>
     */
    public function bindingsFor(int $objectId, int $propertyId): array
    {
        if ($objectId <= 0 || $propertyId <= 0) {
            return [];
        }

        return $this->byProperty[$objectId . ':' . $propertyId] ?? [];
    }

    public function ensureFresh(float $maxAgeSeconds = 0.5): void
    {
        $now = microtime(true);
        if ($this->loadedAt > 0.0 && $now - $this->checkedAt < $maxAgeSeconds) {
            return;
        }
        $this->checkedAt = $now;
        $version = (string)($this->cache->get('mqtt.bindings_version') ?? '0');
        if ($this->loadedAt > 0.0 && $version === $this->version && $now - $this->loadedAt < 30.0) {
            return;
        }
        $this->reload($version);
    }

    public function reload(?string $version = null): void
    {
        $version ??= (string)($this->cache->get('mqtt.bindings_version') ?? '0');
        $bindings = [];
        $records = [];
        $writePaths = [];
        $linkedPrefixes = [];
        $byProperty = [];
        $known = [];

        $rows = $this->topics->all();
        foreach ($rows as $row) {
            $path = (string)$row['path'];
            if ($path === '') {
                continue;
            }
            $known[$path] = (int)$row['id'];
            $write = (string)$row['path_write'];
            if ($write !== '') {
                $writePaths[$write] = 1;
            }
            $configured = $write !== ''
                || (string)$row['replace_list'] !== ''
                || !empty($row['logging'])
                || !empty($row['only_new_value']);
            if ($configured) {
                $records[$path] = $row;
            }
            $object = (string)$row['linked_object'];
            $property = (string)$row['linked_property'];
            $method = (string)$row['linked_method'];
            if ($object === '' || ($property === '' && $method === '')) {
                continue;
            }
            $bindings[$path] = $row;
            foreach (self::ancestors($path) as $prefix) {
                $linkedPrefixes[$prefix] = 1;
            }
            if ($property !== '' && (int)($row['linked_property_id'] ?? 0) > 0) {
                $key = (int)$row['linked_object_id'] . ':' . (int)$row['linked_property_id'];
                $byProperty[$key][] = $row;
            }
        }

        $this->bindings = $bindings;
        $this->records = $records;
        $this->writePaths = $writePaths;
        $this->linkedPrefixes = $linkedPrefixes;
        $this->byProperty = $byProperty;
        $this->known = $known;
        $this->version = $version;
        $this->loadedAt = microtime(true);
    }

    public function invalidate(): void
    {
        $this->cache->increment('mqtt.bindings_version');
        $this->loadedAt = 0.0;
        $this->checkedAt = 0.0;
    }

    /**
     * Путь и все родительские префиксы. Ведущий слеш — часть топика.
     *
     * @return list<string>
     */
    public static function ancestors(string $path): array
    {
        $path = rtrim($path, '/');
        if ($path === '') {
            return [];
        }
        $leading = str_starts_with($path, '/');
        $body = ltrim($path, '/');
        if ($body === '') {
            return [];
        }
        $result = [];
        $current = '';
        foreach (explode('/', $body) as $part) {
            $current = $current === '' ? $part : $current . '/' . $part;
            $result[] = ($leading ? '/' : '') . $current;
        }

        return $result;
    }
}
