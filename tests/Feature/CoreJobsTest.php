<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Queue\Queue;
use PHPUnit\Framework\TestCase;

/**
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 */
final class CoreJobsTest extends TestCase
{
    public function testQueuePageShowsPendingJob(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'cache' => ['driver' => 'array'],
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        $app->container()->make(Queue::class)->push('default', 'demo.job', ['x' => 1]);

        SessionFixture::authenticate($app);

        $response = $app->handle(Request::create('/jobs', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Очередь заданий', (string)$response->getContent());
        self::assertStringContainsString('demo.job', (string)$response->getContent());
    }
}
