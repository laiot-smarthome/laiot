<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Logging\ErrorFileWriter;
use LaIOT\Services\Logging\ErrorRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Ошибка в журнале и отдельный файл: запись, удаление, очистка группы.
 *
 * 2026-10-27 — тест создан вместе с файлами ошибок.
 */
final class ErrorRepositoryTest extends TestCase
{
    private DatabaseManager $database;
    private ErrorRepository $errors;
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/laiot-error-repo-' . bin2hex(random_bytes(4));
        $this->database = new DatabaseManager([
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        foreach ([
            '2026_10_26_000010_create_system_errors.php',
            '2026_10_27_000005_add_system_errors_log_file.php',
        ] as $name) {
            $migration = require dirname(__DIR__, 2) . '/database/migrations/' . $name;
            $migration($this->database->connection());
        }
        $this->errors = new ErrorRepository($this->database, new ErrorFileWriter($this->directory));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
        parent::tearDown();
    }

    public function testRecordStoresFileAndDeleteRemovesIt(): void
    {
        $this->errors->record('error', 'boom', [
            'path' => '/boom',
            'exception' => new RuntimeException('boom detail'),
        ], 'req-9');

        $row = $this->database->connection()->table('system_errors')->first();
        self::assertNotNull($row);
        self::assertNotSame('', (string)$row->log_file);
        self::assertFileExists($this->directory . '/' . $row->log_file);
        self::assertStringContainsString(
            'req-9',
            (string)file_get_contents($this->directory . '/' . $row->log_file)
        );

        $this->errors->delete((int)$row->id);

        self::assertNotNull($this->database->connection()->table('system_errors')
            ->where('id', (int)$row->id)
            ->value('deleted_at'));
        self::assertFileDoesNotExist($this->directory . '/' . $row->log_file);
    }

    public function testDeleteGroupRemovesAllFilesOfGroup(): void
    {
        $this->errors->record('error', 'same', [], 'r1');
        $this->errors->record('error', 'same', [], 'r2');
        $this->errors->record('warning', 'other', [], 'r3');
        $groupFiles = $this->database->connection()->table('system_errors')
            ->where('level', 'error')
            ->pluck('log_file')
            ->all();

        $deleted = $this->errors->deleteGroup('error', 'same');

        self::assertSame(2, $deleted);
        foreach ($groupFiles as $file) {
            self::assertFileDoesNotExist($this->directory . '/' . $file);
        }
        self::assertCount(1, glob($this->directory . '/*') ?: []);
        self::assertCount(1, $this->errors->latest());
    }
}
