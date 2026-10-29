<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Http\Request;
use LaIOT\Http\Middleware\Authorize;
use LaIOT\Http\Session\ArraySession;
use LaIOT\Services\Auth\Authorization;
use PHPUnit\Framework\TestCase;

final class AuthorizationTest extends TestCase
{
    public function testAdminCanAllPermissions(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $authorization = new Authorization($session);

        self::assertTrue($authorization->isAdmin());
        self::assertTrue($authorization->can('panel.view'));
        self::assertTrue($authorization->can('settings.manage'));
    }

    public function testUserHasOnlyPanelViewAndGuestHasNothing(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 2, 'username' => 'user', 'name' => 'User']);
        $authorization = new Authorization($session);

        self::assertFalse($authorization->isAdmin());
        self::assertTrue($authorization->can('panel.view'));
        self::assertFalse($authorization->can('settings.manage'));

        $session->logout();
        self::assertFalse($authorization->can('panel.view'));
    }

    public function testAuthorizeMiddlewareRejectsForbiddenRequest(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 2, 'username' => 'user', 'name' => 'User']);
        $middleware = new Authorize(new Authorization($session));
        $request = Request::create('/admin', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $request->attributes->set('request_id', 'req-1');

        $response = $middleware->handle($request, static fn (): never => throw new \RuntimeException('must not pass'), 'settings.manage');

        self::assertSame(403, $response->getStatusCode());
        $payload = json_decode((string)$response->getContent(), true);
        self::assertSame('forbidden', $payload['error']['code']);
        self::assertSame('req-1', $payload['request_id']);
    }
}
