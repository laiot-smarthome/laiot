<?php

declare(strict_types=1);

namespace MajorDoMo\Modules;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use MajorDoMo\Database\DatabaseManager;
use RuntimeException;

/**
 * Публичные события core-модулей с TTL.
 *
 * Модуль пишет только в namespace `module.<id>.*`; читать может любой
 * core-модуль. Последнее событие сохраняется в БД, пока не истёк TTL.
 * Подписчики в текущем процессе получают событие сразу; для фоновой
 * доставки в будущем будет использована общая очередь.
 */
final class ModuleBus
{
    /** @var array<string, list<callable>> */
    private static array $listeners = [];

    /** @var Closure(): int */
    private readonly Closure $clock;

    public function __construct(
        private readonly string $moduleId,
        private readonly DatabaseManager $database,
        ?Closure $clock = null
    ) {
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $moduleId)) {
            throw new RuntimeException('Некорректный id модуля для ModuleBus.');
        }
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function publish(string $topic, array $payload, int $ttl = 300): void
    {
        $fullTopic = $this->ownerTopic($topic);
        $ttl = max(1, min(86400 * 30, $ttl));
        $now = ($this->clock)();
        $expires = $now + $ttl;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);

        $this->ensureTable();
        $this->database->connection()->table('module_shared_events')->updateOrInsert(
            ['topic' => $fullTopic],
            [
                'module_id' => $this->moduleId,
                'payload' => $json,
                'created_at' => date('Y-m-d H:i:s', $now),
                'updated_at' => date('Y-m-d H:i:s', $now),
                'expires_at' => date('Y-m-d H:i:s', $expires),
                'deleted_at' => null,
            ]
        );

        self::dispatch($fullTopic, $payload);
    }

    /**
     * @return array{topic:string,module:string,payload:array<string,mixed>,updated_at:string,expires_at:string}|null
     */
    public function latest(string $topic): ?array
    {
        $fullTopic = $this->fullTopic($topic);
        $this->ensureTable();
        $row = $this->database->connection()->table('module_shared_events')
            ->where('topic', $fullTopic)
            ->whereNull('deleted_at')
            ->where('expires_at', '>', date('Y-m-d H:i:s', ($this->clock)()))
            ->first();

        if ($row === null) {
            return null;
        }

        $payload = json_decode((string)$row->payload, true);
        if (!is_array($payload)) {
            return null;
        }

        return [
            'topic' => (string)$row->topic,
            'module' => (string)$row->module_id,
            'payload' => $payload,
            'updated_at' => (string)$row->updated_at,
            'expires_at' => (string)$row->expires_at,
        ];
    }

    /**
     * Подписка на события в текущем процессе. Шаблон поддерживает `*`.
     */
    public function subscribe(string $pattern, callable $listener): void
    {
        $pattern = $this->fullTopic($pattern, true);
        self::$listeners[$pattern][] = $listener;
    }

    public static function resetListeners(): void
    {
        self::$listeners = [];
    }

    private function ownerTopic(string $topic): string
    {
        $topic = $this->normalize($topic);
        // Публикация всегда идёт в namespace своего модуля.
        if (str_starts_with($topic, 'module.')) {
            if (!str_starts_with($topic, 'module.' . $this->moduleId . '.')) {
                throw new RuntimeException('Модуль не может публиковать в чужой namespace.');
            }

            return $topic;
        }

        return 'module.' . $this->moduleId . '.' . $topic;
    }

    private function fullTopic(string $topic, bool $allowPattern = false): string
    {
        $topic = $this->normalize($topic, $allowPattern);
        if (str_starts_with($topic, 'module.')) {
            return $topic;
        }

        return 'module.' . $this->moduleId . '.' . $topic;
    }

    private function normalize(string $topic, bool $allowPattern = false): string
    {
        $topic = trim($topic);
        if ($topic === '' || strlen($topic) > 191) {
            throw new RuntimeException('Пустой или слишком длинный topic.');
        }
        if ($allowPattern) {
            if (!preg_match('/^[a-z0-9_.*-]+$/D', $topic)) {
                throw new RuntimeException('Некорректный topic.');
            }
        } elseif (!preg_match('/^[a-z0-9_.-]+$/D', $topic)) {
            throw new RuntimeException('Некорректный topic.');
        }

        return $topic;
    }

    private function ensureTable(): void
    {
        $schema = $this->database->connection()->getSchemaBuilder();
        if ($schema->hasTable('module_shared_events')) {
            return;
        }
        $schema->create('module_shared_events', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('topic', 191)->unique();
            $table->string('module_id', 64);
            $table->text('payload');
            $table->dateTime('expires_at');
            $table->timestamps();
            $table->softDeletes();
            $table->index('expires_at');
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function dispatch(string $topic, array $payload): void
    {
        foreach (self::$listeners as $pattern => $listeners) {
            if (!self::matches($pattern, $topic)) {
                continue;
            }
            foreach ($listeners as $listener) {
                $listener($topic, $payload);
            }
        }
    }

    private static function matches(string $pattern, string $topic): bool
    {
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/D';

        return preg_match($regex, $topic) === 1;
    }
}
