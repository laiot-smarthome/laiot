<?php

declare(strict_types=1);

namespace MajorDoMo\Foundation\Providers;

use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\CallableDispatcher;
use Illuminate\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Illuminate\Routing\Router;
use MajorDoMo\Foundation\ServiceProvider;
use MajorDoMo\Http\Kernel;
use MajorDoMo\Http\Middleware\AuthenticateAdmin;
use MajorDoMo\Http\Middleware\AuthenticateApiToken;
use MajorDoMo\Http\Middleware\Authorize;
use MajorDoMo\Http\Middleware\RequestIdMiddleware;
use MajorDoMo\Http\Middleware\ThrottleLogin;
use MajorDoMo\Http\Middleware\VerifyCsrfToken;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\NativeSession;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Logging\ErrorRepository;
use MajorDoMo\Logging\LoggerFactory;
use MajorDoMo\Logging\RequestContext;
use MajorDoMo\Support\MaintenanceMode;

/**
 * Сессия, HTTP-kernel, middleware и ядровые маршруты.
 *
 * Изменения:
 * 2026-10-28 — kernel получает ViewFactory для HTML-страниц ошибок.
 * 2026-10-26 — kernel использует общие ErrorRepository и MaintenanceMode.
 */
final class HttpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(SessionStore::class, function (): SessionStore {
            $session = $this->app->config('session');

            return $session instanceof SessionStore ? $session : new NativeSession('mydomo', '/');
        });
        $this->container->singleton(PanelRenderer::class);
        $this->container->singleton(CallableDispatcherContract::class, static fn (Container $container): CallableDispatcher => new CallableDispatcher($container));
        $this->container->singleton(Router::class, static function (Container $container): Router {
            $router = new Router($container->make(Dispatcher::class), $container);
            foreach ([
                'request-id' => RequestIdMiddleware::class,
                'auth' => AuthenticateAdmin::class,
                'csrf' => VerifyCsrfToken::class,
                'can' => Authorize::class,
                'api.auth' => AuthenticateApiToken::class,
                'throttle.login' => ThrottleLogin::class,
            ] as $alias => $middleware) {
                $router->aliasMiddleware($alias, $middleware);
            }

            return $router;
        });
        $this->container->singleton(Kernel::class, fn (Container $container): Kernel => new Kernel(
            $container->make(Router::class),
            $container->make(LoggerFactory::class)->channel('http'),
            (bool)$this->app->config('debug', false),
            $container->make(RequestContext::class),
            $container->make(ErrorRepository::class),
            $container->make(MaintenanceMode::class),
            $container->make(ViewFactory::class)
        ));
    }

    public function boot(): void
    {
        $router = $this->container->make(Router::class);
        foreach (['web', 'api'] as $name) {
            $routes = require $this->app->root() . '/routes/' . $name . '.php';
            $routes($router);
        }
    }
}
