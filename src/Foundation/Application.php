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
use MajorDoMo\Audit\ActionLogger;
use MajorDoMo\Audit\ActionLogRepository;
use MajorDoMo\Auth\AdminAuthenticator;
use MajorDoMo\Auth\ApiTokenRepository;
use MajorDoMo\Auth\Authorization;
use MajorDoMo\Auth\RoleRepository;
use MajorDoMo\Auth\UserRepository;
use MajorDoMo\Cache\CacheFactory;
use MajorDoMo\Cache\CacheStore;
use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Database\SettingsRepository;
use MajorDoMo\Docs\KnowledgeBase;
use MajorDoMo\Events\PropertyEventRepository;
use MajorDoMo\History\HistoryRepository;
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
use MajorDoMo\LinkedObject\LinkedObjectService;
use MajorDoMo\Locations\LocationRepository;
use MajorDoMo\Logging\ErrorRepository;
use MajorDoMo\Logging\LoggerFactory;
use MajorDoMo\Logging\RequestContext;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Methods\MethodRunner;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Notifications\NotificationService;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Properties\ValueRepository;
use MajorDoMo\Queue\DatabaseQueue;
use MajorDoMo\Queue\JobRegistry;
use MajorDoMo\Queue\Queue;
use MajorDoMo\Runtime\CodeRunner;
use MajorDoMo\Runtime\TimeoutRepository;
use MajorDoMo\Scripts\ScriptRepository;
use MajorDoMo\Scripts\ScriptRunner;
use MajorDoMo\Search\PanelSearch;
use MajorDoMo\Support\AppContext;
use MajorDoMo\Support\Clock;
use MajorDoMo\Support\HelperLoader;
use MajorDoMo\Support\MaintenanceMode;
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
    private HelperLoader $helperLoader;
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
        $this->container->instance(KnowledgeBase::class, new KnowledgeBase($this->root . '/docs'));
        AppContext::set($this);

        foreach (['var', 'var/cache', 'var/log'] as $directory) {
            $path = $this->root . '/' . $directory;
            if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
                throw new LogicException('Не удалось создать runtime-каталог: ' . $path);
            }
        }

        $timezone = (string)($this->config['timezone'] ?? 'Europe/Moscow');
        if (in_array($timezone, timezone_identifiers_list(), true)) {
            date_default_timezone_set($timezone);
        } else {
            $timezone = date_default_timezone_get();
        }
        $this->clock = new Clock($timezone);
        $this->container->instance(Clock::class, $this->clock);
        $this->helperLoader = new HelperLoader($this->root . '/src/Support/helpers');
        $this->helperLoader->loadCore();
        $this->container->instance(HelperLoader::class, $this->helperLoader);

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
        $this->container->instance(ActionLogRepository::class, new ActionLogRepository($this->database));
        $this->container->instance(ActionLogger::class, new ActionLogger($this->database));
        $this->container->instance(ErrorRepository::class, new ErrorRepository($this->database));
        $this->container->instance(MaintenanceMode::class, new MaintenanceMode($this->root));
        $this->container->instance(LocationRepository::class, new LocationRepository($this->database));
        $scripts = new ScriptRepository($this->database);
        $this->container->instance(ScriptRepository::class, $scripts);
        $this->container->instance(ClassRepository::class, new ClassRepository($this->database));
        $classRepository = $this->container->make(ClassRepository::class);
        $properties = new PropertyRepository($this->database);
        $this->container->instance(PropertyRepository::class, $properties);
        $methods = new MethodRepository($this->database);
        $this->container->instance(MethodRepository::class, $methods);
        $objectRepository = new ObjectRepository($this->database);
        $this->container->instance(ObjectRepository::class, $objectRepository);
        $this->container->instance(
            LinkedObjectService::class,
            new LinkedObjectService($objectRepository, $properties, $methods, $classRepository)
        );
        $methodRunner = new MethodRunner($objectRepository, $methods);
        $this->container->instance(MethodRunner::class, $methodRunner);
        $this->container->instance(TimeoutRepository::class, new TimeoutRepository($this->database));
        $codeRunner = new CodeRunner();
        $this->container->instance(CodeRunner::class, $codeRunner);
        $this->container->instance(ScriptRunner::class, new ScriptRunner($scripts, $codeRunner));
        $history = new HistoryRepository($this->database);
        $this->container->instance(HistoryRepository::class, $history);
        $values = new ValueRepository($this->database, $history);
        $this->container->instance(ValueRepository::class, $values);
        $propertyEvents = new PropertyEventRepository($this->database, $this->clock);
        $this->container->instance(PropertyEventRepository::class, $propertyEvents);
        // Singleton: цепочка хранит текущую глубину и родителя события, пока
        // OnChange-метод выполняет вложенные записи через sg().
        $this->container->instance(PropertyValueService::class, new PropertyValueService(
            $this->database,
            $objectRepository,
            $properties,
            $values,
            $history,
            $methods,
            $methodRunner,
            $propertyEvents,
            $this->logger
        ));
        $this->container->instance(Authorization::class, new Authorization($this->session, $roles));

        $users = new UserRepository($this->database);
        $this->container->instance(UserRepository::class, $users);
        $this->container->instance(AdminAuthenticator::class, new AdminAuthenticator($users));
        $this->container->singleton(
            PanelRenderer::class,
            fn (): PanelRenderer => new PanelRenderer(
                $this->container->make(Factory::class),
                $this->session,
                $this->modules
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
        $this->router->aliasMiddleware('throttle.login', ThrottleLogin::class);
        $this->container->instance(Router::class, $this->router);

        $this->modules = new ModuleManager($this->root, $this->database, $this->container, $this->helperLoader);
        $this->container->instance(ModuleManager::class, $this->modules);
        $this->container->instance(PanelSearch::class, new PanelSearch($this->database, $this->modules));
        $this->container->instance(
            NotificationService::class,
            new NotificationService($this->database, $this->modules, $this->clock)
        );

        $this->kernel = new Kernel(
            $this->router,
            $this->logger,
            (bool)($this->config['debug'] ?? false),
            $this->requestContext,
            new ErrorRepository($this->database),
            new MaintenanceMode($this->root)
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
