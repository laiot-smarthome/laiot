<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\Logging\ErrorFileWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Отдельный файл ошибки: контекст, trace и безопасное удаление.
 *
 * 2026-10-27 — тест создан вместе с ErrorFileWriter.
 */
final class ErrorFileWriterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/mydomo-errors-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
        parent::tearDown();
    }

    public function testWritesContextAndTrace(): void
    {
        $writer = new ErrorFileWriter($this->directory);
        $file = $writer->write(
            7,
            'error',
            'boom',
            ['path' => '/boom', 'class' => RuntimeException::class],
            'req-1',
            new RuntimeException('boom detail')
        );

        self::assertNotNull($file);
        $contents = (string)file_get_contents($this->directory . '/' . $file);
        self::assertStringContainsString('id: 7', $contents);
        self::assertStringContainsString('request_id: req-1', $contents);
        self::assertStringContainsString('boom detail', $contents);
        self::assertStringContainsString('RuntimeException', $contents);
        self::assertStringContainsString('trace:', $contents);
    }

    public function testDeleteRejectsPathTraversalAndRemovesFile(): void
    {
        $writer = new ErrorFileWriter($this->directory);
        $file = $writer->write(1, 'error', 'x', [], 'r');

        self::assertNotNull($file);
        self::assertFalse($writer->delete('../' . $file));
        self::assertTrue($writer->delete($file));
        self::assertFileDoesNotExist($this->directory . '/' . $file);
        self::assertFalse($writer->delete($file));
    }
}
