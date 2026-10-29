<?php

declare(strict_types=1);

namespace LaIOT\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Schema\Builder;
use Throwable;

/**
 * Ленивое подключение к БД через Illuminate Database.
 *
 * Пока это один менеджер на приложение. Он не открывает соединение в
 * конструкторе: health-check и тесты без БД не должны падать на boot.
 * Старый mysqli-слой остаётся в alpha и в новом коде не используется.
 *
 * Изменения:
 * 2026-10-28 — общий resolver доступен валидатору без открытия соединения.
 */
final class DatabaseManager
{
    private ?Capsule $capsule = null;
    private bool $sqliteFunctionsRegistered = false;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function isConfigured(): bool
    {
        return trim((string)($this->config['driver'] ?? '')) !== ''
            && trim((string)($this->config['database'] ?? '')) !== '';
    }

    public function connection(): Connection
    {
        $connection = $this->capsule()->getConnection();
        if ($connection->getDriverName() === 'sqlite' && !$this->sqliteFunctionsRegistered) {
            // Встроенный lower() в SQLite понижает только ASCII, поэтому поиск
            // по русским названиям без своей функции зависит от регистра.
            $connection->getPdo()->sqliteCreateFunction(
                'mjd_lower',
                static fn (mixed $value): string => mb_strtolower((string)$value),
                1
            );
            $this->sqliteFunctionsRegistered = true;
        }

        return $connection;
    }

    /** Общий менеджер соединений для компонентов Illuminate без подключения к БД. */
    public function resolver(): ConnectionResolverInterface
    {
        return $this->capsule()->getDatabaseManager();
    }

    public function schema(): Builder
    {
        return $this->connection()->getSchemaBuilder();
    }

    /**
     * @template T
     * @param callable(Connection): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        /** @phpstan-ignore argument.templateType */
        return $this->connection()->transaction($callback);
    }

    public function ping(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $this->connection()->select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    private function capsule(): Capsule
    {
        if ($this->capsule instanceof Capsule) {
            return $this->capsule;
        }

        $capsule = new Capsule();
        $capsule->addConnection($this->config);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        return $this->capsule = $capsule;
    }
}
