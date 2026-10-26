<?php

declare(strict_types=1);

namespace MajorDoMo\Modules;

use Closure;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use MajorDoMo\Database\DatabaseManager;
use RuntimeException;

/**
 * Доступ core-модуля только к своим таблицам.
 *
 * Модуль получает логические имена (`messages`), а физическое имя всегда
 * получает префикс `module_<id>_`. Любая таблица вне allowlist отвергается.
 * Никакого raw SQL, JOIN и произвольного DDL снаружи нет.
 */
final class ModuleDb
{
    private ?Connection $connection = null;

    /** @var list<string> */
    private array $tables;

    private string $driver;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly string $moduleId,
        private readonly array $config,
        private readonly DatabaseManager $coreDatabase,
        private readonly string $modulePath
    ) {
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $moduleId)) {
            throw new RuntimeException('Некорректный id модуля для ModuleDb.');
        }
        $this->driver = (string)($config['driver'] ?? 'mysql');
        if (!in_array($this->driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Поддерживаются только mysql и sqlite.');
        }
        $tables = $config['tables'] ?? [];
        if (!is_array($tables) || $tables === []) {
            throw new RuntimeException('В storage.tables модуля нет ни одной таблицы.');
        }
        foreach ($tables as $table) {
            if (!is_string($table) || !preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $table)) {
                throw new RuntimeException('Некорректное имя таблицы в storage.tables.');
            }
        }
        $this->tables = array_values($tables);
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function table(string $logical): ModuleQuery
    {
        $physical = $this->physicalName($logical);

        return new ModuleQuery($this->connection()->table($physical));
    }

    /**
     * DDL доступен только для таблиц из storage.tables.
     *
     * @param Closure(Blueprint): void $definition
     */
    public function createTable(string $logical, Closure $definition): void
    {
        $this->connection()->getSchemaBuilder()->create($this->physicalName($logical), $definition);
    }

    public function dropTable(string $logical): void
    {
        $this->connection()->getSchemaBuilder()->dropIfExists($this->physicalName($logical));
    }

    public function physicalName(string $logical): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $logical)) {
            throw new RuntimeException('Некорректное логическое имя таблицы модуля.');
        }
        if (!in_array($logical, $this->tables, true)) {
            throw new RuntimeException('Таблица не принадлежит модулю: ' . $this->moduleId . '.' . $logical);
        }

        return 'module_' . $this->moduleId . '_' . $logical;
    }

    private function connection(): Connection
    {
        if ($this->connection instanceof Connection) {
            return $this->connection;
        }

        if ($this->driver === 'mysql') {
            return $this->connection = $this->coreDatabase->connection();
        }

        $file = (string)($this->config['file'] ?? 'module.sqlite');
        if (!preg_match('/^[A-Za-z0-9_.-]+\.sqlite$/D', $file) || str_contains($file, '..')) {
            throw new RuntimeException('Некорректный файл SQLite модуля.');
        }
        $directory = $this->modulePath . '/data';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Не удалось создать data-каталог модуля.');
        }

        $path = $directory . '/' . $file;
        if (!is_file($path) && !touch($path)) {
            throw new RuntimeException('Не удалось создать SQLite-файл модуля.');
        }

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        return $this->connection = $capsule->getConnection();
    }
}
