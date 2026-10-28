<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use League\Flysystem\CorruptedPathDetected;
use League\Flysystem\StorageAttributes;
use League\Flysystem\SymbolicLinkEncountered;
use League\Flysystem\UnableToListContents;
use MajorDoMo\Files\FileStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Контракт файлов SDK проверяется только в отдельном временном каталоге.
 *
 * Изменения:
 * 2026-10-28 — операции Flysystem, границы модулей и запрет символических ссылок.
 */
final class LocalFilesTest extends TestCase
{
    private string $root;
    private FileStorage $storage;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mydomo-files-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700);
        $this->storage = new FileStorage($this->root . '/files');
    }

    protected function tearDown(): void
    {
        foreach ([$this->root . '/files/modules/demo/link', $this->root . '/files/modules/linked'] as $link) {
            if (is_link($link)) {
                unlink($link);
            }
        }
        (new Filesystem())->deleteDirectory($this->root);
    }

    public function testFileOperationsAndStreamsStayInsideModuleDirectory(): void
    {
        $files = $this->storage->module('demo');
        self::assertDirectoryDoesNotExist($this->root . '/files');
        $files->write('reports/first.txt', 'hello');
        self::assertSame('hello', $files->read('reports/first.txt'));
        self::assertSame(5, $files->fileSize('reports/first.txt'));
        self::assertSame('text/plain', $files->mimeType('reports/first.txt'));
        self::assertGreaterThan(0, $files->lastModified('reports/first.txt'));
        $stream = fopen('php://temp', 'w+');
        self::assertIsResource($stream);
        try {
            fwrite($stream, 'stream');
            rewind($stream);
            $files->writeStream('reports/stream.txt', $stream);
        } finally {
            fclose($stream);
        }
        $read = $files->readStream('reports/stream.txt');
        try {
            self::assertSame('stream', stream_get_contents($read));
        } finally {
            fclose($read);
        }
        $files->copy('reports/first.txt', 'copy.txt');
        $files->move('copy.txt', 'moved.txt');
        self::assertFalse($files->fileExists('copy.txt'));
        self::assertSame('hello', $files->read('moved.txt'));
        self::assertCount(3, $files->listContents('', true)->filter(
            static fn (StorageAttributes $entry): bool => $entry->isFile()
        )->toArray());
        $files->delete('moved.txt');
        $files->deleteDirectory('reports');
        self::assertFalse($files->directoryExists('reports'));
        $files->createDirectory('empty');
        self::assertTrue($files->directoryExists('empty'));
    }

    public function testCoreAndTwoModulesHaveSeparateDisks(): void
    {
        $this->storage->core()->write('same.txt', 'core');
        $this->storage->module('demo')->write('same.txt', 'demo');
        $this->storage->module('neighbor')->write('same.txt', 'neighbor');
        $this->storage->removeModule('demo');
        self::assertFalse($this->storage->module('demo')->fileExists('same.txt'));
        self::assertSame('core', $this->storage->core()->read('same.txt'));
        self::assertSame('neighbor', $this->storage->module('neighbor')->read('same.txt'));
        self::assertDirectoryDoesNotExist($this->root . '/files/modules/demo');
    }

    #[DataProvider('unsafePaths')]
    public function testUnsafePathsAreRejected(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->module('demo')->write($path, 'outside');
    }

    /** @return iterable<string, array{string}> */
    public static function unsafePaths(): iterable
    {
        yield 'parent' => ['../neighbor/secret'];
        yield 'nested parent' => ['safe/../../outside'];
        yield 'backslash parent' => ['..\\neighbor\\secret'];
        yield 'absolute' => ['/etc/passwd'];
        yield 'drive' => ['C:\\outside'];
        yield 'UNC' => ['\\\\server\\share'];
        yield 'stream' => ['php://filter'];
        yield 'alternate stream' => ['file:stream'];
    }

    public function testControlCharactersAreRejected(): void
    {
        $this->expectException(CorruptedPathDetected::class);
        $this->storage->module('demo')->write("bad\0file", 'data');
    }

    public function testInvalidModuleIdCannotSelectAnotherDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->module('../core');
    }

    public function testSymbolicLinksCannotBeReadWrittenOrUsedAsDestinations(): void
    {
        $this->requireLinuxLinks();
        $files = $this->storage->module('demo');
        $files->write('source.txt', 'inside');
        mkdir($this->root . '/outside');
        file_put_contents($this->root . '/outside/secret.txt', 'untouched');
        symlink($this->root . '/outside', $this->root . '/files/modules/demo/link');
        foreach ([
            static fn () => $files->read('link/secret.txt'),
            static fn () => $files->write('link/secret.txt', 'changed'),
            static fn () => $files->fileExists('link/secret.txt'),
            static fn () => $files->copy('source.txt', 'link/copy.txt'),
            static fn () => $files->move('source.txt', 'link/move.txt'),
            static fn () => $files->delete('link/secret.txt'),
        ] as $operation) {
            try {
                $operation();
                self::fail('Операция через символическую ссылку должна отклоняться.');
            } catch (RuntimeException $error) {
                self::assertStringContainsString('ссылки', $error->getMessage());
            }
        }
        self::assertSame('untouched', file_get_contents($this->root . '/outside/secret.txt'));
        self::assertSame('inside', $files->read('source.txt'));
        try {
            $files->deleteDirectory('');
            self::fail('Удаление дерева со ссылкой должно отклоняться до очистки.');
        } catch (UnableToListContents $error) {
            self::assertInstanceOf(SymbolicLinkEncountered::class, $error->getPrevious());
        }
        self::assertSame('untouched', file_get_contents($this->root . '/outside/secret.txt'));
        self::assertSame('inside', $files->read('source.txt'));
    }

    public function testModuleRootCannotBeReplacedWithSymbolicLink(): void
    {
        $this->requireLinuxLinks();
        $this->storage->module('demo')->createDirectory('empty');
        mkdir($this->root . '/outside');
        symlink($this->root . '/outside', $this->root . '/files/modules/linked');
        $this->expectException(RuntimeException::class);
        $this->storage->module('linked')->write('escape.txt', 'outside');
    }

    private function requireLinuxLinks(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            self::markTestSkipped('Символические ссылки файлового SDK проверяются на Linux.');
        }
    }
}
