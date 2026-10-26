<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\Console\AboutCommand;
use MajorDoMo\Console\CacheClearCommand;
use MajorDoMo\Console\DoctorCommand;
use MajorDoMo\Console\HealthCommand;
use MajorDoMo\Console\MigrateCommand;
use MajorDoMo\Console\MigrateStatusCommand;
use MajorDoMo\Console\ModuleListCommand;
use MajorDoMo\Console\QueueFailedCommand;
use MajorDoMo\Console\QueueRetryCommand;
use MajorDoMo\Console\QueueStatsCommand;
use MajorDoMo\Console\QueueWorkCommand;
use MajorDoMo\Console\RouteListCommand;
use MajorDoMo\Foundation\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ConsoleCommandsTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'cache' => ['driver' => 'array'],
            'database' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
        $this->app->boot();
    }

    public function testAboutCommandShowsEnvironment(): void
    {
        $tester = new CommandTester(new AboutCommand($this->app));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('MyDoMo', $tester->getDisplay());
        self::assertStringContainsString('PHP:', $tester->getDisplay());
        self::assertStringContainsString('Core modules: 4', $tester->getDisplay());
    }

    public function testDoctorCommandReportsEnvironment(): void
    {
        $tester = new CommandTester(new DoctorCommand($this->app));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Database', $tester->getDisplay());
        self::assertStringContainsString('Cache', $tester->getDisplay());
    }

    public function testHealthCommandChecksDatabaseAndCache(): void
    {
        $tester = new CommandTester(new HealthCommand($this->app));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('database: ok', $tester->getDisplay());
        self::assertStringContainsString('cache: ok', $tester->getDisplay());
    }

    public function testCacheClearCommandRemovesValues(): void
    {
        $this->app->cache()->put('console.test', 'value');

        $tester = new CommandTester(new CacheClearCommand($this->app));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertNull($this->app->cache()->get('console.test'));
    }

    public function testModuleListWithoutModules(): void
    {
        $tester = new CommandTester(new ModuleListCommand($this->app));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Shoutbox', $tester->getDisplay());
    }

    public function testQueueStatsShowsEmptyQueue(): void
    {
        $tester = new CommandTester(new QueueStatsCommand($this->app));
        $tester->execute(['--queue' => 'default']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('default', $tester->getDisplay());
        self::assertStringContainsString('Pending', $tester->getDisplay());
    }

    public function testQueueWorkProcessesRegisteredJob(): void
    {
        $called = 0;
        $this->app->jobs()->register('demo.job', static function () use (&$called): void {
            $called++;
        });
        $this->app->queue()->push('default', 'demo.job');

        $tester = new CommandTester(new QueueWorkCommand($this->app));
        $tester->execute(['--once' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(1, $called);
        self::assertStringContainsString('Обработано заданий: 1', $tester->getDisplay());
    }

    public function testQueueFailedListAndRetryMissing(): void
    {
        $failed = new CommandTester(new QueueFailedCommand($this->app));
        $failed->execute(['--queue' => 'default']);
        self::assertSame(0, $failed->getStatusCode());
        self::assertStringContainsString('Упавших заданий нет', $failed->getDisplay());

        $retry = new CommandTester(new QueueRetryCommand($this->app));
        $retry->execute(['id' => '999']);
        self::assertSame(1, $retry->getStatusCode());
        self::assertStringContainsString('не найдено', $retry->getDisplay());
    }

    public function testMigrationsCanBeRunAndListed(): void
    {
        $migrate = new CommandTester(new MigrateCommand($this->app));
        $migrate->execute([]);
        self::assertSame(0, $migrate->getStatusCode());
        self::assertStringContainsString('Применена', $migrate->getDisplay());

        $status = new CommandTester(new MigrateStatusCommand($this->app));
        $status->execute([]);
        self::assertSame(0, $status->getStatusCode());
        self::assertStringContainsString('2026_10_26_000001_create_queue_jobs.php', $status->getDisplay());
    }

    public function testRouteListShowsHealthRoute(): void
    {
        $tester = new CommandTester(new RouteListCommand($this->app));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('/health', $tester->getDisplay());
        self::assertStringContainsString('health', $tester->getDisplay());
    }
}
