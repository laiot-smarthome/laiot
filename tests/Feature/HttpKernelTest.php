<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\ErrorResponse;
use MajorDoMo\Http\Kernel;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Logging\RequestContext;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * 2026-10-29 — debug показывает стек и исходник; обычный режим скрывает детали.
 * 2026-10-29 — неверная структура входных полей возвращает 400 без утечки деталей.
 * 2026-10-26 — действующая сессия и проверка скрытия деталей ошибок вне debug.
 */
final class HttpKernelTest extends TestCase
{
    private Application $app;

    public function testMalformedQueryReturnsBadRequestForHtmlAndJson(): void
    {
        foreach (['text/html', 'application/json'] as $accept) {
            $response = $this->app->handle(Request::create('/classes', 'GET', ['q' => ['unexpected']], [], [], [
                'HTTP_ACCEPT' => $accept,
            ]));
            self::assertSame(400, $response->getStatusCode());
            self::assertNotEmpty($response->headers->get('X-Request-Id'));
            self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
            $content = (string)$response->getContent();
            self::assertStringNotContainsString('InputBag', $content);
            self::assertStringNotContainsString('non-scalar', $content);
            if ($accept === 'application/json') {
                self::assertSame('bad_request', json_decode($content, true)['error']['code']);
            } else {
                self::assertStringContainsString('Некорректный запрос', $content);
            }
        }
    }

    protected function setUp(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin']);
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'view_cache' => sys_get_temp_dir() . '/mydomo-views-' . bin2hex(random_bytes(4)),
            'log' => 'php://stderr',
            'session' => $session,
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $this->app->boot();
        SessionFixture::authenticate($this->app);
    }

    public function testBladeAndContainerWork(): void
    {
        $response = $this->app->handle(Request::create('/yoyo-demo', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Yoyo + Blade', (string)$response->getContent());
        self::assertStringContainsString('increment', (string)$response->getContent());
        self::assertSame('yes', $response->headers->get('X-MyDoMo-Middleware'));
        self::assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function testHealthWithoutDatabase(): void
    {
        $app = new Application(dirname(__DIR__, 2), [
            'session' => new ArraySession(),
            'cache' => ['driver' => 'array'],
            'log' => 'php://stderr',
        ]);
        $response = $app->handle(Request::create('/health', 'GET'));

        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString('"status":"degraded"', (string)$response->getContent());
        self::assertStringContainsString('"database":"not_configured"', (string)$response->getContent());
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    public function testRootRedirectsToAdminWhenLoggedIn(): void
    {
        $response = $this->app->handle(Request::create('/', 'GET'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin', $response->headers->get('Location'));
    }

    public function testNotFound(): void
    {
        $response = $this->app->handle(Request::create('/missing', 'GET'));

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('text/html', (string)$response->headers->get('Content-Type'));

        $html = (string)$response->getContent();
        self::assertStringContainsString('md-error-page', $html);
        self::assertStringContainsString('Страница не найдена', $html);
        self::assertStringContainsString('/assets/img/maskot/find.png', $html);
        self::assertStringContainsString('data-error-code="not_found"', $html);
        self::assertStringContainsString('/missing', $html);
        self::assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function testMethodNotAllowed(): void
    {
        $response = $this->app->handle(Request::create('/logout', 'GET'));

        self::assertSame(405, $response->getStatusCode());
        self::assertStringContainsString('POST', (string)$response->headers->get('Allow'));
        self::assertStringContainsString('Метод не поддерживается', (string)$response->getContent());
    }

    public function testNotFoundReturnsJsonForApiRequest(): void
    {
        $response = $this->app->handle(Request::create('/missing', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]));

        self::assertSame(404, $response->getStatusCode());
        $payload = json_decode((string)$response->getContent(), true);
        self::assertIsArray($payload);
        self::assertFalse($payload['ok']);
        self::assertSame('not_found', $payload['error']['code']);
        self::assertNotEmpty($payload['request_id']);
    }

    public function testErrorFallsBackToTextWithoutView(): void
    {
        // Middleware и отдельные kernel-конструкторы могут работать без
        // ViewFactory: ответ обязан сохранить статус и request id.
        $kernel = new Kernel($this->app->router(), new NullLogger(), context: new RequestContext());

        $response = $kernel->handle(Request::create('/missing', 'GET'));

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('text/plain', (string)$response->headers->get('Content-Type'));
        self::assertStringContainsString('Not Found', (string)$response->getContent());
        self::assertStringContainsString('request_id:', (string)$response->getContent());
    }

    public function testBrokenViewFallsBackToText(): void
    {
        $view = $this->createMock(ViewFactory::class);
        $view->method('make')->willThrowException(new \RuntimeException('view down'));

        $response = ErrorResponse::make(
            Request::create('/missing', 'GET'),
            404,
            'not_found',
            'Not Found',
            'req-1',
            view: $view
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('text/plain', (string)$response->headers->get('Content-Type'));
        self::assertStringContainsString('request_id: req-1', (string)$response->getContent());
    }

    public function testDebugErrorShowsTraceAndSource(): void
    {
        $this->app->router()->get('/boom', static function (): never {
            throw new \RuntimeException('boom detail');
        });

        $response = $this->app->handle(Request::create('/boom', 'GET'));

        self::assertSame(500, $response->getStatusCode());

        $html = (string)$response->getContent();
        $requestId = (string)$response->headers->get('X-Request-Id');
        self::assertStringContainsString('Ошибка приложения', $html);
        self::assertStringContainsString('Трассировка', $html);
        self::assertStringContainsString('data-error-code="server_error"', $html);
        self::assertStringContainsString('Код запроса', $html);
        self::assertStringContainsString($requestId, $html);
        self::assertStringContainsString('boom detail', $html);
        self::assertStringContainsString('tests/Feature/HttpKernelTest.php', $html);
        self::assertStringContainsString('md-exception__line is-error', $html);
        self::assertStringContainsString('no-store', (string)$response->headers->get('Cache-Control'));
    }

    public function testNamedRouteIsRegistered(): void
    {
        $route = $this->app->router()->getRoutes()->getByName('health');

        self::assertNotNull($route);
        self::assertSame('health', $route->uri());
    }

    public function testDefaultConfigurationHidesExceptionDetails(): void
    {
        $app = new Application(dirname(__DIR__, 2), [
            'session' => new ArraySession(),
            'cache' => ['driver' => 'array'],
            'log' => 'php://stderr',
        ]);
        $app->boot();
        $app->router()->get('/boom', static function (): never {
            throw new \RuntimeException('private error detail');
        });
        foreach (['text/html', 'application/json'] as $accept) {
            $response = $app->handle(Request::create('/boom', 'GET', [], [], [], ['HTTP_ACCEPT' => $accept]));
            self::assertSame(500, $response->getStatusCode());
            self::assertStringNotContainsString('private error detail', (string)$response->getContent());
        }
    }
}
