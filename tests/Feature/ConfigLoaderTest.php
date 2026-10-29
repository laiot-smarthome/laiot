<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\Foundation\Application;
use LaIOT\Foundation\ConfigLoader;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    public function testSectionsAreLoadedFromSeparateFiles(): void
    {
        $config = ConfigLoader::load(dirname(__DIR__, 2));

        self::assertSame('file', $config['cache']['driver']);
        self::assertSame('queue_jobs', $config['queue']['table']);
        self::assertArrayHasKey('logging', $config);
    }

    public function testApplicationUsesLoadedSections(): void
    {
        $app = new Application(dirname(__DIR__, 2), [
            'log' => 'php://stderr',
            'database' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
        $app->boot();

        self::assertSame('queue_jobs', $app->config('queue.table'));
        self::assertSame(3, $app->config('queue.max_attempts'));
    }
}
