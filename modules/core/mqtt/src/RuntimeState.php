<?php

declare(strict_types=1);

namespace MajorDoMo\Mqtt;

use MajorDoMo\Cache\CacheStore;

/**
 * Состояние и счётчики MQTT-цикла.
 *
 * Счётчики копятся в памяти процесса и сбрасываются в CacheStore раз в
 * несколько секунд: Redis-раундтрип на каждое сообщение не нужен. Страница
 * читает снимок и heartbeat, чтобы отличить «нет сообщений» от «процесс
 * приёма не запущен».
 *
 * 2026-10-27 — наблюдаемость MQTT без внешнего монитора (alpha runtime).
 */
final class RuntimeState
{
    public const STATUS_KEY = 'mqtt.status';

    public const COUNTERS_KEY = 'mqtt.counters';

    public const HEARTBEAT_KEY = 'mqtt.heartbeat';

    public const CONFIG_KEY = 'mqtt.settings_version';

    /** @var array<string, int> */
    private array $counters = [];

    /** @var array<string, array{count:int,sum:float,last:float}> */
    private array $timings = [
        'inbound' => ['count' => 0, 'sum' => 0.0, 'last' => 0.0],
        'publish' => ['count' => 0, 'sum' => 0.0, 'last' => 0.0],
    ];

    private float $lastMessageAt = 0.0;

    private ?string $lastError = null;

    private float $lastErrorAt = 0.0;

    private ?bool $connected = null;

    private float $connectedSince = 0.0;

    private float $flushedAt = 0.0;

    public function __construct(
        private readonly CacheStore $cache,
        private readonly Settings $settings
    ) {
    }

    public function count(string $name, int $by = 1): void
    {
        $this->counters[$name] = ($this->counters[$name] ?? 0) + $by;
    }

    public function received(): void
    {
        $this->count('received');
        $this->markMessage();
    }

    public function applied(float $milliseconds = 0.0): void
    {
        $this->count('applied');
        $this->sample('inbound', $milliseconds);
    }

    public function published(float $milliseconds = 0.0): void
    {
        $this->count('published');
        $this->sample('publish', $milliseconds);
    }

    public function error(string $message): void
    {
        $this->count('errors');
        $this->lastError = mb_substr($message, 0, 1000);
        $this->lastErrorAt = microtime(true);
    }

    public function markMessage(): void
    {
        $this->lastMessageAt = microtime(true);
    }

    public function setConnected(bool $connected, ?string $error = null): void
    {
        if ($error !== null) {
            $this->error($error);
        }
        if ($this->connected === $connected) {
            return;
        }
        $this->connected = $connected;
        if ($connected) {
            $this->connectedSince = microtime(true);
            $this->lastError = null;
            $this->lastErrorAt = 0.0;
        }
        $this->flush(true);
    }

    public function connected(): bool
    {
        return $this->connected === true;
    }

    /**
     * Сбрасывает накопленные счётчики и, для процесса приёма, heartbeat.
     */
    public function flush(bool $force = false, bool $heartbeat = true): void
    {
        $now = microtime(true);
        if (!$force && $now - $this->flushedAt < 5.0) {
            return;
        }
        $this->flushedAt = $now;

        $counters = $this->cache->get(self::COUNTERS_KEY);
        $counters = is_array($counters) ? $counters : [];
        foreach ($this->counters as $name => $by) {
            $counters[$name] = (int)($counters[$name] ?? 0) + $by;
        }
        $this->cache->put(self::COUNTERS_KEY, $counters);

        $this->cache->put(self::STATUS_KEY, [
            'connected' => (bool)$this->connected,
            'connected_since' => $this->connectedSince > 0 ? (int)$this->connectedSince : null,
            'last_message_at' => $this->lastMessageAt > 0 ? (int)$this->lastMessageAt : null,
            'last_error' => $this->lastError,
            'last_error_at' => $this->lastErrorAt > 0 ? (int)$this->lastErrorAt : null,
            'inbound_ms' => $this->average('inbound'),
            'publish_ms' => $this->average('publish'),
            'settings_version' => (string)($this->cache->get(self::CONFIG_KEY) ?? '0'),
        ]);

        if ($heartbeat) {
            $this->cache->put(self::HEARTBEAT_KEY, [
                'pid' => getmypid() ?: 0,
                'at' => time(),
                'connected' => (bool)$this->connected,
            ], 30);
        }

        $this->counters = [];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $status = $this->cache->get(self::STATUS_KEY);
        $status = is_array($status) ? $status : [];
        $counters = $this->cache->get(self::COUNTERS_KEY);
        $heartbeat = $this->cache->get(self::HEARTBEAT_KEY);
        $heartbeat = is_array($heartbeat) ? $heartbeat : [];
        $alive = (int)($heartbeat['at'] ?? 0) > 0 && time() - (int)$heartbeat['at'] <= 30;

        return [
            'connected' => (bool)($status['connected'] ?? false),
            'connected_since' => (int)($status['connected_since'] ?? 0),
            'last_message_at' => (int)($status['last_message_at'] ?? 0),
            'last_error' => (string)($status['last_error'] ?? ''),
            'last_error_at' => (int)($status['last_error_at'] ?? 0),
            'inbound_ms' => (float)($status['inbound_ms'] ?? 0.0),
            'publish_ms' => (float)($status['publish_ms'] ?? 0.0),
            'counters' => is_array($counters) ? $counters : [],
            'worker_alive' => $alive,
            'worker_pid' => (int)($heartbeat['pid'] ?? 0),
            'heartbeat_at' => (int)($heartbeat['at'] ?? 0),
            'broker' => $this->settings->string('host') . ':' . $this->settings->int('port'),
        ];
    }

    public function resetCounters(): void
    {
        $this->cache->forget(self::COUNTERS_KEY);
        $this->cache->forget(self::STATUS_KEY);
        $this->cache->forget(self::HEARTBEAT_KEY);
    }

    private function sample(string $stage, float $milliseconds): void
    {
        if ($milliseconds < 0) {
            return;
        }
        $this->timings[$stage]['count']++;
        $this->timings[$stage]['sum'] += $milliseconds;
        $this->timings[$stage]['last'] = $milliseconds;
    }

    private function average(string $stage): float
    {
        $timing = $this->timings[$stage];
        if ($timing['count'] === 0) {
            return 0.0;
        }

        return round($timing['sum'] / $timing['count'], 1);
    }
}
