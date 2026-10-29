<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Modules\ModuleDb;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ModuleDbTest extends TestCase
{
    private string $modulePath;
    private ModuleDb $db;

    protected function setUp(): void
    {
        $this->modulePath = sys_get_temp_dir() . '/laiot-module-' . bin2hex(random_bytes(4));
        mkdir($this->modulePath, 0775, true);

        $this->db = new ModuleDb('shoutbox', [
            'driver' => 'sqlite',
            'file' => 'module.sqlite',
            'tables' => ['messages'],
        ], new DatabaseManager([]), $this->modulePath);

        $this->db->createTable('messages', static function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('MESSAGE');
        });
    }

    protected function tearDown(): void
    {
        $this->db->dropTable('messages');
        (new Filesystem())->deleteDirectory($this->modulePath);
        parent::tearDown();
    }

    public function testTableIsPrefixedAndCrudWorks(): void
    {
        self::assertSame('module_shoutbox_messages', $this->db->physicalName('messages'));

        $this->db->table('messages')->insert(['MESSAGE' => 'hello']);
        self::assertSame('hello', $this->db->table('messages')->value('MESSAGE'));
        self::assertNotNull($this->db->table('messages')->first());

        $this->db->table('messages')->where('MESSAGE', 'hello')->update(['MESSAGE' => 'updated']);
        self::assertSame('updated', $this->db->table('messages')->value('MESSAGE'));
        self::assertSame(1, $this->db->table('messages')->count());

        $this->db->table('messages')->delete();
        self::assertSame(0, $this->db->table('messages')->count());
    }

    public function testForeignTableIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->db->table('users');
    }

    public function testRawQueryMethodsAreNotExposed(): void
    {
        $methods = get_class_methods($this->db->table('messages'));

        self::assertNotContains('join', $methods);
        self::assertNotContains('raw', $methods);
        self::assertNotContains('whereRaw', $methods);
        self::assertNotContains('selectRaw', $methods);
    }

    public function testTransactionRollsBackAndReturnsValue(): void
    {
        $value = $this->db->transaction(function (): string {
            $this->db->table('messages')->insert(['MESSAGE' => 'kept']);

            return 'result';
        });
        self::assertSame('result', $value);
        self::assertSame(1, $this->db->table('messages')->count());

        try {
            $this->db->transaction(function (): void {
                $this->db->table('messages')->insert(['MESSAGE' => 'rolled back']);
                throw new RuntimeException('stop');
            });
        } catch (RuntimeException $error) {
            // Ожидаемо: граница транзакции не глотает ошибку модуля.
            self::assertSame('stop', $error->getMessage());
        }

        // Если исключение не вышло, вставка осталась бы в таблице.
        self::assertSame(0, $this->db->table('messages')->where('MESSAGE', 'rolled back')->count());
    }

    public function testSqliteFileIsCreatedInModuleDataDirectory(): void
    {
        $this->db->table('messages')->insert(['MESSAGE' => 'persisted']);

        self::assertFileExists($this->modulePath . '/data/module.sqlite');
    }
}
