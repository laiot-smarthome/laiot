<?php

declare(strict_types=1);

namespace MajorDoMo\Foundation;

use Clickfwd\Yoyo\Blade\YoyoBladeCompilerEngine;
use Clickfwd\Yoyo\Blade\YoyoBladeDirectives;
use Clickfwd\Yoyo\ViewProviders\BladeViewProvider;
use Clickfwd\Yoyo\Yoyo;
use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Routing\CallableDispatcher;
use Illuminate\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use LogicException;
use MajorDoMo\Auth\AdminAuthenticator;
use MajorDoMo\Auth\ApiTokenRepository;
use MajorDoMo\Auth\Authorization;
use MajorDoMo\Auth\RoleRepository;
use MajorDoMo\Cache\CacheFactory;
use MajorDoMo\Cache\CacheStore;
use MajorDoMo\Core\Users\UserAccounts;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Database\SettingsRepository;
use MajorDoMo\Http\Kernel;
use MajorDoMo\Http\Middleware\AuthenticateAdmin;
use MajorDoMo\Http\Middleware\AuthenticateApiToken;
use MajorDoMo\Http\Middleware\Authorize;
use MajorDoMo\Http\Middleware\RequestIdMiddleware;
use MajorDoMo\Http\Middleware\VerifyCsrfToken;
use MajorDoMo\Http\Session\NativeSession;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Logging\LoggerFactory;
use MajorDoMo\Logging\RequestContext;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Queue\DatabaseQueue;
use MajorDoMo\Queue\JobRegistry;
use MajorDoMo\Queue\Queue;
use MajorDoMo\Support\Clock;
use MajorDoMo\View\BladeComponents;
use MajorDoMo\View\BladeView;
use MajorDoMo\Yoyo\Counter;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Каркас MyDoMo: контейнер, конфиг, логгер, Blade, роутер и HTTP-kernel.
 *
 * Это вертикальный срез нового ядра. Он не подключает legacy loader,
 * лениво открывает PDO-соединение через Illuminate Database, содержит
 * сессию, вход администратора и CSRF.
 */
final class Application
{
    private Container $container;
    private Router $router;
    private Kernel $kernel;
    private LoggerInterface $logger;
    private LoggerFactory $loggerFactory;
    private RequestContext $requestContext;
    private Clock $clock;
    private DatabaseManager $database;
    private CacheStore $cache;
    private Queue $queue;
    private SessionStore $session;
    private ModuleManager $modules;
    private Yoyo $yoyo;

    /** @var array<string, mixed> */
    private array $config;

    private bool $booted = false;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly string $root, array $config = [])
    {
        $defaults = ConfigLoader::load($root);
        $this->config = array_replace_recursive($defaults, $config);
    }

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        $this->container = new Container();
        Container::setInstance($this->container);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(self::class, $this);

        $timezone = (string)($this->config['timezone'] ?? 'Europe/Moscow');
        if (in_array($timezone, timezone_identifiers_list(), true)) {
            date_default_timezone_set($timezone);
        } else {
            $timezone = date_default_timezone_get();
        }
        $this->clock = new Clock($timezone);
        $this->container->instance(Clock::class, $this->clock);

        $this->requestContext = new RequestContext();
        $this->container->instance(RequestContext::class, $this->requestContext);
        $this->loggerFactory = new LoggerFactory([
            'path' => $this->config['log'] ?? 'php://stderr',
            'debug' => (bool)($this->config['debug'] ?? false),
        ], $this->requestContext);
        $this->container->instance(LoggerFactory::class, $this->loggerFactory);
        $this->logger = $this->loggerFactory->channel('app');
        $this->container->instance(LoggerInterface::class, $this->logger);

        $this->database = new DatabaseManager((array)($this->config['database'] ?? []));
        $this->container->instance(DatabaseManager::class, $this->database);
        $this->container->instance(SettingsRepository::class, new SettingsRepository($this->database));

        $this->cache = CacheFactory::create((array)($this->config['cache'] ?? []), $this->database);
        $this->container->instance(CacheStore::class, $this->cache);

        $this->queue = new DatabaseQueue($this->database, (array)($this->config['queue'] ?? []));
        $this->container->instance(Queue::class, $this->queue);
        $this->container->instance(JobRegistry::class, new JobRegistry());

        $session = $this->config['session'] ?? null;
        $this->session = $session instanceof SessionStore
            ? $session
            : new NativeSession('mydomo', '/');
        $this->container->instance(SessionStore::class, $this->session);
        $roles = new RoleRepository($this->database);
        $this->container->instance(RoleRepository::class, $roles);
        $this->container->instance(ApiTokenRepository::class, new ApiTokenRepository($this->database));
        $this->container->instance(Authorization::class, new Authorization($this->session, $roles));

        $this->container->bind(
            UserAccounts::class,
            fn (): UserAccounts => new UserAccounts($this->database->connection()->getPdo())
        );
        $this->container->bind(
            AdminAuthenticator::class,
            fn (): AdminAuthenticator => new AdminAuthenticator(
                fn (): UserAccounts => $this->container->make(UserAccounts::class)
            )
        );

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
        $this->router->aliasMiddleware('request-id', RequestIdMiddleware::class);
        $this->router->aliasMiddleware('auth', AuthenticateAdmin::class);
        $this->router->aliasMiddleware('csrf', VerifyCsrfToken::class);
        $this->router->aliasMiddleware('can', Authorize::class);
        $this->router->aliasMiddleware('api.auth', AuthenticateApiToken::class);
        $this->container->instance(Router::class, $this->router);

        $this->modules = new ModuleManager($this->root, $this->database, $this->container);
        $this->container->instance(ModuleManager::class, $this->modules);

        $this->kernel = new Kernel(
            $this->router,
            $this->logger,
            (bool)($this->config['debug'] ?? false),
            $this->requestContext
        );
        $this->container->instance(Kernel::class, $this->kernel);

        $routes = require $this->root . '/routes/web.php';
        $routes($this->router);
        $apiRoutes = require $this->root . '/routes/api.php';
        $apiRoutes($this->router);
        $this->modules->register($this->router, $view);

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

        // Контроллеры получают текущий Request через DI, а не из глобальных
        // суперглобалов: иначе тесты и вложенные вызовы видят пустой запрос.
        $this->container->instance(Request::class, $request);

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

    public function clock(): Clock
    {
        $this->ensureBooted();

        return $this->clock;
    }

    public function logger(): LoggerInterface
    {
        $this->ensureBooted();

        return $this->logger;
    }

    public function database(): DatabaseManager
    {
        $this->ensureBooted();

        return $this->database;
    }

    public function cache(): CacheStore
    {
        $this->ensureBooted();

        return $this->cache;
    }

    public function queue(): Queue
    {
        $this->ensureBooted();

        return $this->queue;
    }

    public function jobs(): JobRegistry
    {
        $this->ensureBooted();

        return $this->container->make(JobRegistry::class);
    }

    public function yoyo(): Yoyo
    {
        $this->ensureBooted();

        return $this->yoyo;
    }

    public function session(): SessionStore
    {
        $this->ensureBooted();

        return $this->session;
    }

    public function modules(): ModuleManager
    {
        $this->ensureBooted();

        return $this->modules;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    private function createViewFactory(Dispatcher $events): Factory
    {
        $files = new Filesystem();
        $cache = (string)($this->config['view_cache'] ?? $this->root . '/var/cache/views');
        if (!is_dir($cache) && !mkdir($cache, 0775, true) && !is_dir($cache)) {
            throw new LogicException('Не удалось создать каталог шаблонов: ' . $cache);
        }

        $views = (string)($this->config['views'] ?? $this->root . '/resources/views');
        $resolver = new EngineResolver();
        $compiler = new BladeCompiler($files, $cache);
        $resolver->register(
            'blade',
            static fn (): YoyoBladeCompilerEngine => new YoyoBladeCompilerEngine($compiler, $files)
        );
        new YoyoBladeDirectives($compiler);

        $finder = new FileViewFinder($files, [$views, $views . '/yoyo']);
        $factory = new Factory($resolver, $finder, $events);
        $bladeView = new BladeView($factory, $this->container);

        // Yoyo и Blade-компоненты ищут эти сервисы через контейнер.
        $this->container->instance('blade.compiler', $compiler);
        $this->container->instance('view.engine.resolver', $resolver);
        $this->container->instance(Factory::class, $factory);
        $this->container->instance(ViewFactory::class, $factory);
        $this->container->instance('view', $bladeView);
        BladeComponents::register($compiler, $views);
        $this->container->instance(BladeView::class, $bladeView);
        $this->container->instance('config', new Fluent([
            'view' => [
                'compiled' => $cache,
                'paths' => [$views],
            ],
        ]));

        $this->yoyo = new Yoyo();
        $this->yoyo->configure([
            'url' => '/yoyo',
            'scriptsPath' => '/assets/vendor/yoyo',
            'namespace' => 'MajorDoMo\\Yoyo\\',
            'htmx' => '/assets/vendor/htmx/htmx.min.js',
        ]);
        $this->yoyo->registerViewProvider(
            static fn (): BladeViewProvider => new BladeViewProvider($bladeView)
        );
        Yoyo::registerComponent('counter', Counter::class);
        $this->container->instance(Yoyo::class, $this->yoyo);

        return $factory;
    }

    private function ensureBooted(): void
    {
        if (!$this->booted) {
            throw new LogicException('Сначала вызовите boot().');
        }
    }
}
