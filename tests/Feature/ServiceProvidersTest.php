<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\View\Factory;
use LogicException;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Foundation\ServiceProvider;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Runtime\CodeRunner;
use MajorDoMo\Support\MaintenanceMode;
use MajorDoMo\View\BladeView;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * Контракт расширяемой сборки: две фазы, ленивые singleton и общий kernel.
 *
 * Изменения:
 * 2026-10-26 — регрессии сервис-провайдеров на изолированном приложении.
 */
final class ServiceProvidersTest extends TestCase
{
    public function testAllRegistrationsPrecedeBootAndServicesStayLazy(): void
    {
        $probe = new ProviderProbe();
        $app = $this->application([
            'test_probe' => $probe,
            'providers' => [
                ...$this->providers(),
                ConsumerProbeProvider::class,
                DependencyProbeProvider::class,
                ConsumerProbeProvider::class,
            ],
        ]);
        self::assertSame($app, $app->boot());
        $app->boot();
        self::assertSame([
            'consumer.register', 'dependency.register', 'consumer.boot', 'dependency.boot',
        ], $probe->events);
        self::assertSame(0, $probe->created);

        $runner = $app->container()->make(CodeRunner::class);
        self::assertSame($runner, $app->container()->make(CodeRunner::class));
        self::assertSame(1, $probe->created);
        self::assertNotNull($app->router()->getRoutes()->getByName('provider.probe'));
        $response = $app->handle(Request::create('/provider-probe'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('provider ready', $response->getContent());
    }

    public function testAliasesAndPublicAccessorsResolveSharedInstances(): void
    {
        $app = $this->application()->boot();
        $container = $app->container();
        self::assertSame($app->queue(), $app->queue());
        self::assertSame($app->database(), $app->database());
        self::assertSame($app->session(), $app->session());
        self::assertSame($container->make(Factory::class), $container->make(ViewFactory::class));
        self::assertSame($container->make(BladeView::class), $container->make('view'));
        self::assertSame(
            $container->make(PropertyValueService::class),
            $container->make(PropertyValueService::class)
        );
    }

    public function testKernelAndControllerUseOverriddenMaintenanceService(): void
    {
        $root = sys_get_temp_dir() . '/mydomo-provider-' . bin2hex(random_bytes(6));
        $mode = new MaintenanceMode($root);
        $app = $this->application([
            'test_maintenance' => $mode,
            'providers' => [...$this->providers(), MaintenanceProbeProvider::class],
        ])->boot();
        $app->session()->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        SessionFixture::authenticate($app);

        try {
            $enable = $app->handle(Request::create('/admin/maintenance/enable', 'POST', [
                '_token' => $app->session()->csrfToken(),
            ]));
            self::assertSame(302, $enable->getStatusCode());
            self::assertTrue($mode->enabled());
            self::assertSame(503, $app->handle(Request::create('/admin'))->getStatusCode());
        } finally {
            $mode->disable();
            if (is_dir($root . '/var')) {
                rmdir($root . '/var');
            }
            if (is_dir($root)) {
                rmdir($root);
            }
        }
    }

    public function testInvalidProviderIsRejectedBeforeRegistration(): void
    {
        $app = $this->application(['providers' => [stdClass::class]]);
        $this->expectException(LogicException::class);
        $app->boot();
    }

    /** @param array<string, mixed> $config */
    private function application(array $config = []): Application
    {
        return new Application(dirname(__DIR__, 2), array_replace([
            'session' => new ArraySession(),
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'log' => 'php://stderr',
        ], $config));
    }

    /** @return list<class-string<ServiceProvider>> */
    private function providers(): array
    {
        return require dirname(__DIR__, 2) . '/config/providers.php';
    }
}

/** 2026-10-26 — состояние проверки порядка запуска и ленивой фабрики. */
final class ProviderProbe
{
    /** @var list<string> */
    public array $events = [];
    public int $created = 0;
}

/** 2026-10-26 — boot потребляет зависимость, регистрируемую следующим провайдером. */
final class ConsumerProbeProvider extends ServiceProvider
{
    public function register(): void
    {
        $probe = $this->app->config('test_probe');
        if (!$probe instanceof ProviderProbe) {
            throw new LogicException('Не передано состояние тестового провайдера.');
        }
        $this->container->instance(ProviderProbe::class, $probe);
        $probe->events[] = 'consumer.register';
        $this->container->singleton(CodeRunner::class, static function () use ($probe): CodeRunner {
            $probe->created++;

            return new CodeRunner();
        });
    }

    public function boot(): void
    {
        $probe = $this->container->make(ProviderProbe::class);
        if ($this->container->make('provider.dependency') !== $probe) {
            throw new LogicException('Зависимость следующего провайдера недоступна.');
        }
        $probe->events[] = 'consumer.boot';
        $this->container->make(Router::class)->get('/provider-probe', static fn (): Response => new Response('provider ready'))
            ->name('provider.probe');
    }
}

/** 2026-10-26 — зависимость и boot второго провайдера. */
final class DependencyProbeProvider extends ServiceProvider
{
    public function register(): void
    {
        $probe = $this->container->make(ProviderProbe::class);
        $probe->events[] = 'dependency.register';
        $this->container->instance('provider.dependency', $probe);
    }

    public function boot(): void
    {
        $this->container->make(ProviderProbe::class)->events[] = 'dependency.boot';
    }
}

/** 2026-10-26 — подмена одного сервиса должна действовать во всех потребителях. */
final class MaintenanceProbeProvider extends ServiceProvider
{
    public function register(): void
    {
        $mode = $this->app->config('test_maintenance');
        if (!$mode instanceof MaintenanceMode) {
            throw new LogicException('Не передан тестовый режим обслуживания.');
        }
        $this->container->instance(MaintenanceMode::class, $mode);
    }
}
