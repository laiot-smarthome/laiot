<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/mydomo-migrations-' . bin2hex(random_bytes(4));
        mkdir($this->directory, 0775, true);
        file_put_contents($this->directory . '/001_create_demo.php', <<<'PHP'
<?php
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
return static function (Connection $connection): void {
    $connection->getSchemaBuilder()->create('demo', static function (Blueprint $table): void {
        $table->increments('ID');
        $table->string('NAME');
    });
};
PHP);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function testMigrationsRunOnceAndAreReported(): void
    {
        $database = new DatabaseManager([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $migrator = new Migrator($database, $this->directory);

        self::assertSame(['001_create_demo.php'], $migrator->migrate());
        self::assertTrue($database->schema()->hasTable('demo'));
        self::assertSame([], $migrator->migrate());

        $status = $migrator->status();
        self::assertCount(1, $status);
        self::assertTrue($status[0]['applied']);
        self::assertSame(1, $status[0]['batch']);
    }
}
