<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class YoyoTest extends TestCase
{
    private Application $app;
    private ArraySession $session;

    protected function setUp(): void
    {
        Yoyo::request()->reset();
        $this->session = new ArraySession();
        $this->session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin']);
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'view_cache' => sys_get_temp_dir() . '/mydomo-views-' . bin2hex(random_bytes(4)),
            'log' => 'php://stderr',
            'session' => $this->session,
        ]);
        $this->app->boot();
    }

    protected function tearDown(): void
    {
        Yoyo::request()->reset();
        unset($_SERVER['HTTP_HX_REQUEST'], $_SERVER['REQUEST_METHOD']);
        $_REQUEST = [];
        parent::tearDown();
    }

    public function testBladePageRendersYoyoComponent(): void
    {
        $response = $this->app->handle(Request::create('/yoyo-demo', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('yoyo', (string)$response->getContent());
        self::assertStringContainsString('increment', (string)$response->getContent());
        self::assertStringContainsString('yoyo.js', (string)$response->getContent());
    }

    public function testYoyoUpdateReturnsRenderedComponent(): void
    {
        $_REQUEST = ['component' => 'counter/increment', 'yoyo-id' => 'test-counter'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        Yoyo::request()->mock($_REQUEST, $_SERVER);

        $response = $this->app->handle(Request::create(
            '/yoyo',
            'POST',
            [],
            [],
            [],
            ['HTTP_X_CSRF_TOKEN' => $this->session->csrfToken()]
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('1', (string)$response->getContent());
    }

    public function testYoyoUpdateRejectsMissingCsrf(): void
    {
        $_REQUEST = ['component' => 'counter/increment', 'yoyo-id' => 'test-counter'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        Yoyo::request()->mock($_REQUEST, $_SERVER);

        $response = $this->app->handle(Request::create('/yoyo', 'POST'));

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('csrf_failed', (string)$response->getContent());
    }

    public function testYoyoUpdateRequiresAuthentication(): void
    {
        $this->session->logout();

        $response = $this->app->handle(Request::create('/yoyo', 'POST'));

        self::assertSame(401, $response->getStatusCode());
    }
}
