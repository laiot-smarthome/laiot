<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use MajorDoMo\Logging\LoggerFactory;
use MajorDoMo\Logging\RequestContext;
use PHPUnit\Framework\TestCase;

final class LoggingTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/mydomo-log-' . bin2hex(random_bytes(4));
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
}
