<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use MajorDoMo\Console\PruneCommand;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Maintenance\RetentionPruner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Retention: старые история, журнал, задания, таймеры и ошибки удаляются,
 * ожидающие задания и невыполненные таймеры остаются.
 *
 * 2026-10-27 — тест создан вместе с db:prune и core-worker maintenance.
 */
final class RetentionPrunerTest extends TestCase
{
    private string $errorDirectory = '';

    public function testDryRunCountsAndPruneDeletesOnlyOldRows(): void
    {
        $app = $this->boot();
        $old = date('Y-m-d H:i:s', time() - 40 * 86400);
        $new = date('Y-m-d H:i:s');
        $file = '20260101-000000-1-abcdef.log';
        file_put_contents($this->errorDirectory . '/' . $file, 'trace line');

        $connection = $app->database()->connection();
        $connection->table('property_history')->insert([
            ['id' => 1, 'created_at' => $old],
            ['id' => 2, 'created_at' => $new],
        ]);
        $connection->table('property_events')->insert([
            ['id' => 1, 'created_at' => $old],
            ['id' => 2, 'created_at' => $new],
        ]);
        $connection->table('queue_jobs')->insert([
            ['id' => 1, 'queue' => 'module.demo', 'failed_at' => $old],
            ['id' => 2, 'queue' => 'module.demo', 'failed_at' => $new],
            ['id' => 3, 'queue' => 'module.demo', 'failed_at' => null],
        ]);
        $connection->table('runtime_timeouts')->insert([
            ['id' => 1, 'executed_at' => $old, 'deleted_at' => null],
            ['id' => 2, 'executed_at' => null, 'deleted_at' => $old],
            ['id' => 3, 'executed_at' => $new, 'deleted_at' => null],
            ['id' => 4, 'executed_at' => null, 'deleted_at' => null],
        ]);
        $connection->table('system_errors')->insert([
            ['id' => 1, 'level' => 'error', 'message' => 'old', 'request_id' => '', 'log_file' => $file, 'created_at' => $old],
            ['id' => 2, 'level' => 'error', 'message' => 'new', 'request_id' => '', 'log_file' => null, 'created_at' => $new],
        ]);

        $pruner = $app->container()->make(RetentionPruner::class);
        $counts = $pruner->prune(true);
        self::assertSame(1, $counts['property_history']);
        self::assertSame(1, $counts['property_events']);
        self::assertSame(1, $counts['queue_jobs']);
        self::assertSame(2, $counts['runtime_timeouts']);
        self::assertSame(1, $counts['system_errors']);
        self::assertSame(2, $connection->table('property_history')->count());
        self::assertSame(3, $connection->table('queue_jobs')->count());
        self::assertSame(2, $connection->table('system_errors')->count());
        self::assertFileExists($this->errorDirectory . '/' . $file);

        $tester = new CommandTester(new PruneCommand($pruner));
        $tester->execute(['--dry-run' => true]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('dry-run', $tester->getDisplay());

        $pruner->prune(false);
        self::assertSame(1, $connection->table('property_history')->count());
        self::assertSame(1, $connection->table('property_events')->count());
        self::assertSame(2, $connection->table('queue_jobs')->count());
        self::assertSame(1, $connection->table('queue_jobs')->whereNull('failed_at')->count());
        self::assertSame(2, $connection->table('runtime_timeouts')->count());
        self::assertSame(1, $connection->table('system_errors')->count());
        self::assertFileDoesNotExist($this->errorDirectory . '/' . $file);
    }

    public function testZeroDaysDisablesSection(): void
    {
        $app = $this->boot(['history_days' => 0]);
        $old = date('Y-m-d H:i:s', time() - 400 * 86400);
        $app->database()->connection()->table('property_history')->insert(['id' => 1, 'created_at' => $old]);

        $counts = $app->container()->make(RetentionPruner::class)->prune();

        self::assertSame(0, $counts['property_history']);
        self::assertSame(1, $app->database()->connection()->table('property_history')->count());
    }

    /**
     * @param array<string, mixed> $retention
     */
    private function boot(array $retention = []): Application
    {
        $this->errorDirectory = sys_get_temp_dir() . '/mydomo_retention_' . bin2hex(random_bytes(4));
        if (!mkdir($this->errorDirectory, 0775, true) && !is_dir($this->errorDirectory)) {
            self::fail('Не удалось создать каталог ошибок');
        }

        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'logging' => ['error_directory' => $this->errorDirectory],
            'retention' => $retention + [
                'history_days' => 30,
                'events_days' => 30,
                'queue_days' => 30,
                'timeouts_days' => 30,
                'errors_days' => 30,
                'batch' => 100,
                'interval' => 600,
            ],
        ]);
        $app->boot();
        $this->createSchema($app);

        return $app;
    }

    private function createSchema(Application $app): void
    {
        $schema = $app->database()->schema();
        $schema->create('property_history', static function (Blueprint $table): void {
            $table->increments('id');
            $table->dateTime('created_at')->nullable();
        });
        $schema->create('property_events', static function (Blueprint $table): void {
            $table->increments('id');
            $table->dateTime('created_at')->nullable();
        });
        $schema->create('queue_jobs', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('queue', 64);
            $table->dateTime('failed_at')->nullable();
        });
        $schema->create('runtime_timeouts', static function (Blueprint $table): void {
            $table->increments('id');
            $table->dateTime('executed_at')->nullable();
            $table->dateTime('deleted_at')->nullable();
        });
        $schema->create('system_errors', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('level', 16);
            $table->text('message');
            $table->string('request_id', 64)->default('');
            $table->string('log_file', 255)->nullable();
            $table->dateTime('created_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        if ($this->errorDirectory !== '' && is_dir($this->errorDirectory)) {
            foreach (glob($this->errorDirectory . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->errorDirectory);
        }
    }
}
