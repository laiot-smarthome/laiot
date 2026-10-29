<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use LaIOT\Services\Logging\LoggerFactory;
use LaIOT\Services\Logging\RequestContext;
use PHPUnit\Framework\TestCase;

final class LoggingTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/laiot-log-' . bin2hex(random_bytes(4));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function testChannelWritesRequestIdAndChannelName(): void
    {
        $context = new RequestContext();
        $context->setRequestId('req-123');

        $factory = new LoggerFactory([
            'path' => $this->directory . '/app.log',
            'debug' => true,
        ], $context);

        $factory->channel('http')->info('request handled');
        $factory->channel('security')->warning('login rejected');

        $contents = (string)file_get_contents($this->directory . '/app.log');
        self::assertStringContainsString('http.INFO', $contents);
        self::assertStringContainsString('request handled', $contents);
        self::assertStringContainsString('security.WARNING', $contents);
        self::assertStringContainsString('req-123', $contents);
    }

    public function testChannelsWriteSeparateRotatingFiles(): void
    {
        $context = new RequestContext();
        $factory = new LoggerFactory([
            'directory' => $this->directory,
            'level' => 'info',
            'max_files' => 2,
        ], $context);

        $factory->channel('http')->info('request handled');
        $factory->channel('security')->warning('login rejected');
        $factory->channel('queue')->error('job failed');

        $http = glob($this->directory . '/http-*.log') ?: [];
        $security = glob($this->directory . '/security-*.log') ?: [];
        $queue = glob($this->directory . '/queue-*.log') ?: [];

        self::assertCount(1, $http);
        self::assertCount(1, $security);
        self::assertCount(1, $queue);
        self::assertStringContainsString('request handled', (string)file_get_contents($http[0]));
        self::assertStringContainsString('login rejected', (string)file_get_contents($security[0]));
        self::assertStringContainsString('job failed', (string)file_get_contents($queue[0]));
        self::assertStringNotContainsString('login rejected', (string)file_get_contents($http[0]));
    }

    public function testStreamPathKeepsSingleOutput(): void
    {
        $factory = new LoggerFactory([
            'path' => $this->directory . '/all.log',
            'debug' => true,
        ], new RequestContext());

        $factory->channel('http')->info('a');
        $factory->channel('security')->info('b');

        $contents = (string)file_get_contents($this->directory . '/all.log');
        self::assertStringContainsString('http.INFO', $contents);
        self::assertStringContainsString('security.INFO', $contents);
        self::assertSame([], glob($this->directory . '/http-*.log') ?: []);
    }
}
