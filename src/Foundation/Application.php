<?php

declare(strict_types=1);

namespace MajorDoMo\Foundation;

use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Routing\CallableDispatcher;
use Illuminate\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use LogicException;
use MajorDoMo\Http\Kernel;
use MajorDoMo\Http\Middleware\RequestIdMiddleware;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Каркас MyDoMo: контейнер, конфиг, логгер, Blade, роутер и HTTP-kernel.
 *
 * Это первый вертикальный срез нового ядра. Он намеренно не подключает
 * legacy loader и не зависит от базы: задача среза — доказать, что выбранный
 * стек собирается и отдаёт запрос. Следующие этапы добавляют Eloquent,
 * сессии, auth и модули.
 */
final class Application
{
    private Container $container;
    private Router $router;
    private Kernel $kernel;
    private LoggerInterface $logger;

    /** @var array<string, mixed> */
    private array $config;

    private bool $booted = false;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly string $root, array $config = [])
    {
        $defaults = require $root . '/config/app.php';
        $this->config = array_replace_recursive($defaults, $config);
    }

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        $this->container = new Container();
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(self::class, $this);
        $this->container->instance('config', $this->config);

        $this->logger = $this->createLogger();
        $this->container->instance(LoggerInterface::class, $this->logger);

        $events = new Dispatcher($this->container);
        $this->container->instance(Dispatcher::class, $events);

        $view = $this->createViewFactory($events);
        $this->container->instance(Factory::class, $view);
        $this->container->instance(ViewFactory::class, $view);

        $this->container->instance(
            CallableDispatcherContract::class,
            new CallableDispatcher($this->container)
        );

        $this->router = new Router($events, $this->container);
        $this->router->aliasMiddleware('spike-request', RequestIdMiddleware::class);
        $this->container->instance(Router::class, $this->router);

        $this->kernel = new Kernel($this->router, $this->logger, (bool)($this->config['debug'] ?? false));
        $this->container->instance(Kernel::class, $this->kernel);

        $routes = require $this->root . '/routes/web.php';
        $routes($this->router);

        // Имена задаются после добавления маршрута, поэтому справочники
        // нужно обновить явно: иначе getByName() не найдёт маршрут.
        $this->router->getRoutes()->refreshNameLookups();
        $this->router->getRoutes()->refreshActionLookups();

        $this->booted = true;

        return $this;
    }

    public function handle(Request $request): Response
    {
        if (!$this->booted) {
            $this->boot();
        }

        return $this->kernel->handle($request);
    }

    public function root(): string
    {
        return $this->root;
    }

    public function container(): Container
    {
        $this->ensureBooted();

        return $this->container;
    }

    public function router(): Router
    {
        $this->ensureBooted();

        return $this->router;
    }

    public function logger(): LoggerInterface
    {
        $this->ensureBooted();

        return $this->logger;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    private function createLogger(): LoggerInterface
    {
        $path = (string)($this->config['log'] ?? 'php://stderr');
        if (!str_contains($path, '://')) {
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new LogicException('Не удалось создать каталог логов: ' . $directory);
            }
        }

        $level = (bool)($this->config['debug'] ?? false) ? Level::Debug : Level::Info;
        $logger = new Logger('mydomo');
        $logger->pushHandler(new StreamHandler($path, $level));

        return $logger;
    }

    private function createViewFactory(Dispatcher $events): Factory
    {
        $files = new Filesystem();
        $cache = (string)($this->config['view_cache'] ?? $this->root . '/var/cache/views');
        if (!is_dir($cache) && !mkdir($cache, 0775, true) && !is_dir($cache)) {
            throw new LogicException('Не удалось создать каталог шаблонов: ' . $cache);
        }

        $resolver = new EngineResolver();
        $compiler = new BladeCompiler($files, $cache);
        $resolver->register('blade', static fn (): CompilerEngine => new CompilerEngine($compiler, $files));

        $finder = new FileViewFinder($files, [(string)($this->config['views'] ?? $this->root . '/resources/views')]);

        return new Factory($resolver, $finder, $events);
    }

    private function ensureBooted(): void
    {
        if (!$this->booted) {
            throw new LogicException('Сначала вызовите boot().');
        }
    }
}
