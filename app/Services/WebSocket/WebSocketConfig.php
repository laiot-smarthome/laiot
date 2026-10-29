<?php

declare(strict_types=1);

namespace LaIOT\Services\WebSocket;

/**
 * Нормализованная конфигурация WebSocket.
 *
 * Значения приходят из config/websocket.php и .env-схемы; сервис не читает
 * окружение сам. Границы полей здесь те же, что в редакторе настроек, чтобы
 * опечатка в .env не выключила сервер или не создала бесконечный цикл.
 *
 * 2026-10-29 — конфигурация выделена для CLI, HTTP-выдачи тикета и сервера.
 */
final readonly class WebSocketConfig
{
    /**
     * @param list<string> $allowedOrigins
     */
    public function __construct(
        public bool $enabled,
        public string $host,
        public int $port,
        public string $path,
        public string $publicUrl,
        public int $workers,
        public int $ticketTtl,
        public int $pollMs,
        public int $batch,
        public int $replayLimit,
        public int $maxChannels,
        public int $maxConnections,
        public int $heartbeat,
        public int $authCheck,
        public int $pruneInterval,
        public int $retentionHours,
        public string $rootDomain,
        public array $allowedOrigins
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $origins = [];
        $rawOrigins = $config['allowed_origins'] ?? [];
        if (is_string($rawOrigins)) {
            $rawOrigins = explode(',', $rawOrigins);
        }
        if (is_array($rawOrigins)) {
            foreach ($rawOrigins as $origin) {
                if (!is_string($origin)) {
                    continue;
                }
                $origin = rtrim(trim($origin), '/');
                if ($origin !== '' && preg_match('#^https?://[^/\s]+$#iD', $origin) === 1
                    && !in_array($origin, $origins, true)) {
                    $origins[] = $origin;
                }
            }
        }

        $publicUrl = rtrim(trim((string)($config['public_url'] ?? '')), '/');
        $rootDomain = self::host((string)($config['root_domain'] ?? ''));
        if ($rootDomain === '') {
            $rootDomain = self::host($publicUrl);
        }
        if ($rootDomain === '') {
            $rootDomain = self::host((string)($config['base_url'] ?? ''));
        }

        return new self(
            self::bool($config['enabled'] ?? true),
            self::text((string)($config['host'] ?? ''), '127.0.0.1'),
            self::int($config['port'] ?? 8081, 1, 65535, 8081),
            self::path((string)($config['path'] ?? '')),
            $publicUrl,
            self::int($config['workers'] ?? 2, 1, 64, 2),
            self::int($config['ticket_ttl'] ?? 300, 30, 86400, 300),
            self::int($config['poll_ms'] ?? 100, 20, 5000, 100),
            self::int($config['batch'] ?? 500, 1, 5000, 500),
            self::int($config['replay_limit'] ?? 500, 1, 5000, 500),
            self::int($config['max_channels'] ?? 2048, 1, 8192, 2048),
            self::int($config['max_connections'] ?? 1000, 1, 100000, 1000),
            self::int($config['heartbeat'] ?? 25, 5, 300, 25),
            self::int($config['auth_check'] ?? 30, 5, 300, 30),
            self::int($config['prune_interval'] ?? 300, 30, 3600, 300),
            self::int($config['retention_hours'] ?? 24, 1, 720, 24),
            $rootDomain,
            $origins
        );
    }

    /** Адрес, который слушает Workerman. */
    public function listenAddress(): string
    {
        return 'websocket://' . $this->host . ':' . $this->port;
    }

    /** Публичный ws(s)-адрес; пусто означает «тот же хост, что у панели». */
    public function publicUrl(): string
    {
        return $this->publicUrl;
    }

    /**
     * Origin разрешён, если он входит в точный список либо относится к
     * корневому домену или его поддомену. Пустой Origin допускается только
     * без точного списка: браузер его всегда присылает, а небраузерный
     * клиент всё равно обязан предъявить тикет.
     */
    public function originAllowed(string $origin, string $requestHost = ''): bool
    {
        if ($this->allowedOrigins !== []) {
            return $origin !== '' && in_array(rtrim($origin, '/'), $this->allowedOrigins, true);
        }
        if ($origin === '') {
            return true;
        }
        $scheme = parse_url($origin, PHP_URL_SCHEME);
        if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            return false;
        }
        $originHost = self::host($origin);
        if ($originHost === '') {
            return false;
        }
        $root = $this->rootDomain !== '' ? $this->rootDomain : self::host($requestHost);
        if ($root === '') {
            return false;
        }

        return $originHost === $root || str_ends_with($originHost, '.' . $root);
    }

    /**
     * Достаёт домен из URL или отдельного значения. Поддерживает голый домен
     * и localhost/IP; невалидное значение превращается в пустую строку.
     */
    private static function host(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $host = parse_url($value, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            $host = parse_url('http://' . ltrim($value, '/'), PHP_URL_HOST);
        }
        if (!is_string($host) || $host === '') {
            return '';
        }
        $host = strtolower(rtrim(trim($host, '[]'), '.'));
        if ($host === '') {
            return '';
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || $host === 'localhost') {
            return $host;
        }
        if (strlen($host) > 253
            || preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/D', $host) !== 1) {
            return '';
        }

        return $host;
    }

    private static function text(string $value, string $default): string
    {
        $value = trim($value);

        return $value !== '' ? $value : $default;
    }

    private static function path(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '/websocket';
        }
        if (!str_starts_with($value, '/')) {
            $value = '/' . $value;
        }

        return rtrim($value, '/') ?: '/';
    }

    private static function int(mixed $value, int $min, int $max, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }
        $number = (int)$value;

        return $number >= $min && $number <= $max ? $number : $default;
    }

    private static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
        }

        return false;
    }
}
