<?php

declare(strict_types=1);

namespace MajorDoMo\Database;

use Illuminate\Database\Schema\Blueprint;
use RuntimeException;
use Throwable;

/**
 * Файловые миграции ядра.
 *
 * Файл миграции возвращает callable(Connection). Учёт применённых версий
 * хранится в `migrations`; повторный запуск ничего не делает.
 */
final class Migrator
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly string $path
    ) {
    }

    /**
     * @return list<array{name:string,applied:bool,batch:int|null,ran_at:string|null}>
     */
    public function status(): array
    {
        $applied = $this->applied();
        $rows = [];
        foreach ($this->files() as $file) {
            $name = basename($file);
            $rows[] = [
                'name' => $name,
                'applied' => isset($applied[$name]),
                'batch' => $applied[$name]['batch'] ?? null,
                'ran_at' => $applied[$name]['ran_at'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @return list<string> имена применённых миграций
     */
    public function migrate(): array
    {
        $this->ensureRepository();
        $applied = $this->applied();
        $batch = $this->nextBatch();
        $ran = [];

        foreach ($this->files() as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                continue;
            }

            $migration = require $file;
            if (!is_callable($migration)) {
                throw new RuntimeException('Миграция должна возвращать callable: ' . $name);
            }

            try {
                $migration($this->database->connection());
            } catch (Throwable $error) {
                throw new RuntimeException('Миграция не выполнена: ' . $name . ': ' . $error->getMessage(), 0, $error);
            }

            $this->database->connection()->table('migrations')->insert([
                'migration' => $name,
                'batch' => $batch,
                'ran_at' => date('Y-m-d H:i:s'),
            ]);
            $ran[] = $name;
        }

        return $ran;
    }

    /**
     * @return array<string, array{batch:int,ran_at:string}>
     */
    private function applied(): array
    {
        $this->ensureRepository();
        $result = [];
        foreach ($this->database->connection()->table('migrations')->get() as $row) {
            $result[(string)$row->migration] = [
                'batch' => (int)$row->batch,
                'ran_at' => (string)$row->ran_at,
            ];
        }

        return $result;
    }

    private function nextBatch(): int
    {
        $max = $this->database->connection()->table('migrations')->max('batch');

        return (int)$max + 1;
    }

    private function ensureRepository(): void
    {
        $schema = $this->database->connection()->getSchemaBuilder();
        if ($schema->hasTable('migrations')) {
            return;
        }

        $schema->create('migrations', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('migration', 191)->unique();
            $table->integer('batch');
            $table->dateTime('ran_at');
        });
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        if (!is_dir($this->path)) {
            return [];
        }

        $files = glob($this->path . '/*.php') ?: [];
        sort($files, SORT_STRING);

        return $files;
    }
}
