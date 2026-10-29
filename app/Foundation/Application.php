<?php

declare(strict_types=1);

namespace LaIOT\Foundation;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use LaIOT\Database\DatabaseManager;
use LaIOT\Http\Kernel;
use LaIOT\Http\Session\SessionStore;
use LaIOT\Services\Cache\CacheStore;
use LaIOT\Services\Modules\ModuleManager;
use LaIOT\Services\Queue\JobRegistry;
use LaIOT\Services\Queue\Queue;
use LaIOT\Support\AppContext;
use LaIOT\Support\Clock;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Жизненный цикл приложения и доступ к общему контейнеру.
 *
 * Подсистемы собираются сервис-провайдерами: сначала регистрация всех
 * зависимостей, затем boot в порядке config/providers.php.
 *
 * Изменения:
 * 2026-10-29 — дочерние core-процессы получают снимок действующей конфигурации.
 * 2026-10-26 — сборка подсистем вынесена в провайдеры, публичный API сохранён.
 * 2026-10-27 — контейнер отдаёт Blade минимальный Foundation-адаптер, чтобы
 *              компилировались новые анонимные компоненты.
 */
final class Application
{
    private Container $container;

    /** @var array<string, mixed> */
    private array $config;

    private bool $booted = false;
    private bool $booting = false;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly string $root, array $config = [])
    {
        $this->config = array_replace_recursive(ConfigLoader::load($root), $config);
        if (array_key_exists('providers', $config)) {
            // Порядок списка имеет значение: явная настройка заменяет его целиком.
            $this->config['providers'] = $config['providers'];
        }
    }

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }
        if ($this->booting) {
            throw new LogicException('Повторный вызов boot() во время запуска приложения.');
        }

        $this->booting = true;
        try {
            $this->container = new Container();
            Container::setInstance($this->container);
            $this->container->instance(Container::class, $this->container);
            $this->container->instance(self::class, $this);
            // Blade ищет пространство имён компонентов через Foundation-
            // контракт; полного Laravel нет, поэтому отдаём минимальный адаптер.
            $this->container->instance(
                ApplicationContract::class,
                new BladeComponentNamespace('LaIOT')
            );
            AppContext::set($this);

            $providers = $this->providers();
            foreach ($providers as $provider) {
                $provider->register();
            }
            foreach ($providers as $provider) {
                $provider->boot();
            }

            // Имена назначаются после добавления маршрутов, включая маршруты
            // модулей и дополнительных провайдеров: обновляем справочники в конце.
            $routes = $this->container->make(Router::class)->getRoutes();
            $routes->refreshNameLookups();
            $routes->refreshActionLookups();
            $this->booted = true;
        } finally {
            $this->booting = false;
        }

        return $this;
    }

    public function handle(Request $request): Response
    {
        $this->boot();
        // Текущий Request нужен DI контроллеров, включая тестовые запросы.
        $this->container->instance(Request::class, $request);

        return $this->container->make(Kernel::class)->handle($request);
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
        return $this->container()->make(Router::class);
    }

    public function clock(): Clock
    {
        return $this->container()->make(Clock::class);
    }

    public function logger(): LoggerInterface
    {
        return $this->container()->make(LoggerInterface::class);
    }

    public function database(): DatabaseManager
    {
        return $this->container()->make(DatabaseManager::class);
    }

    public function cache(): CacheStore
    {
        return $this->container()->make(CacheStore::class);
    }

    public function queue(): Queue
    {
        return $this->container()->make(Queue::class);
    }

    public function jobs(): JobRegistry
    {
        return $this->container()->make(JobRegistry::class);
    }

    public function yoyo(): Yoyo
    {
        return $this->container()->make(Yoyo::class);
    }

    public function session(): SessionStore
    {
        return $this->container()->make(SessionStore::class);
    }

    public function modules(): ModuleManager
    {
        return $this->container()->make(ModuleManager::class);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    /**
     * Полная конфигурация для новых процессов без повторного чтения .env:
     * дочерний WebSocket должен сохранить переданные приложению overrides.
     * 2026-10-29 — снимок конфигурации для core-worker websocket.
     * @return array<string, mixed>
     */
    public function configuration(): array
    {
        return $this->config;
    }

    /**
     * @return list<ServiceProvider>
     */
    private function providers(): array
    {
        $classes = $this->config('providers');
        if (!is_array($classes) || $classes === []) {
            throw new LogicException('Не задан список сервис-провайдеров.');
        }
        $providers = [];
        foreach ($classes as $class) {
            if (!is_string($class) || !is_subclass_of($class, ServiceProvider::class)) {
                throw new LogicException('Провайдер должен наследовать ' . ServiceProvider::class . '.');
            }
            if (!isset($providers[$class])) {
                $providers[$class] = new $class($this, $this->container);
            }
        }

        return array_values($providers);
    }

    private function ensureBooted(): void
    {
        if (!$this->booted) {
            throw new LogicException('Сначала вызовите boot().');
        }
    }
}
