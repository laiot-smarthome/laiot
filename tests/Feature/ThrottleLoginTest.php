<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Http\Request;
use LaIOT\Database\DatabaseManager;
use LaIOT\Http\Middleware\ThrottleLogin;
use LaIOT\Services\Cache\CacheFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2026-10-26 — сброс лимита использует актуальный адрес /admin.
 */
final class ThrottleLoginTest extends TestCase
{
    public function testSixthAttemptIsRejected(): void
    {
        $middleware = new ThrottleLogin(CacheFactory::create(['driver' => 'array'], new DatabaseManager([])));
        $request = Request::create('/login', 'POST', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $request->attributes->set('request_id', 'req-1');
        $next = static fn (): Response => new Response('', 200);

        for ($i = 0; $i < 5; $i++) {
            self::assertSame(200, $middleware->handle($request, $next)->getStatusCode());
        }

        $response = $middleware->handle($request, $next);
        self::assertSame(429, $response->getStatusCode());
        self::assertStringContainsString('too_many_requests', (string)$response->getContent());
    }

    public function testSuccessfulLoginResetsAttempts(): void
    {
        $middleware = new ThrottleLogin(CacheFactory::create(['driver' => 'array'], new DatabaseManager([])));
        $request = Request::create('/login', 'POST', [], [], [], ['REMOTE_ADDR' => '10.0.0.2']);
        $request->attributes->set('request_id', 'req-2');

        for ($i = 0; $i < 4; $i++) {
            $middleware->handle($request, static fn (): Response => new Response('', 200));
        }
        $middleware->handle($request, static fn (): Response => new Response('', 302, ['Location' => '/admin']));

        self::assertSame(200, $middleware->handle($request, static fn (): Response => new Response('', 200))->getStatusCode());
    }
}
