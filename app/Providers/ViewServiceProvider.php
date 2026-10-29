<?php

declare(strict_types=1);

namespace LaIOT\Providers;

use Clickfwd\Yoyo\Blade\YoyoBladeCompilerEngine;
use Clickfwd\Yoyo\Blade\YoyoBladeDirectives;
use Clickfwd\Yoyo\ViewProviders\BladeViewProvider;
use Clickfwd\Yoyo\Yoyo;
use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Fluent;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use LaIOT\Foundation\ServiceProvider;
use LaIOT\View\BladeComponents;
use LaIOT\View\BladeView;
use LogicException;

/**
 * Blade, компоненты и адаптер Yoyo с общими экземплярами и aliases.
 *
 * Изменения:
 * 2026-10-26 — настройка представлений вынесена из Application.
 */
final class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(Filesystem::class);
        $this->container->singleton(BladeCompiler::class, function (Container $container): BladeCompiler {
            $cache = (string)$this->app->config('view_cache', $this->app->root() . '/var/cache/views');
            if (!is_dir($cache) && !mkdir($cache, 0775, true) && !is_dir($cache)) {
                throw new LogicException('Не удалось создать каталог шаблонов: ' . $cache);
            }
            $compiler = new BladeCompiler($container->make(Filesystem::class), $cache);
            new YoyoBladeDirectives($compiler);
            BladeComponents::register($compiler, $this->views());

            return $compiler;
        });
        $this->container->alias(BladeCompiler::class, 'blade.compiler');
        $this->container->singleton(EngineResolver::class, static function (Container $container): EngineResolver {
            $resolver = new EngineResolver();
            $resolver->register('blade', static fn (): YoyoBladeCompilerEngine => new YoyoBladeCompilerEngine(
                $container->make(BladeCompiler::class),
                $container->make(Filesystem::class)
            ));

            return $resolver;
        });
        $this->container->alias(EngineResolver::class, 'view.engine.resolver');
        $this->container->singleton(Factory::class, fn (Container $container): Factory => new Factory(
            $container->make(EngineResolver::class),
            new FileViewFinder($container->make(Filesystem::class), [$this->views(), $this->views() . '/yoyo']),
            $container->make(Dispatcher::class)
        ));
        $this->container->alias(Factory::class, ViewFactory::class);
        $this->container->singleton(BladeView::class);
        $this->container->alias(BladeView::class, 'view');
        $this->container->singleton('config', fn (): Fluent => new Fluent([
            'view' => [
                'compiled' => (string)$this->app->config('view_cache', $this->app->root() . '/var/cache/views'),
                'paths' => [$this->views()],
            ],
        ]));
        $this->container->singleton(Yoyo::class, static function (Container $container): Yoyo {
            $bladeView = $container->make(BladeView::class);
            $yoyo = new Yoyo();
            $yoyo->configure([
                'url' => '/yoyo',
                'scriptsPath' => '/assets/vendor/yoyo',
                'namespace' => 'LaIOT\\Yoyo\\',
                'htmx' => '/assets/vendor/htmx/htmx.min.js',
            ]);
            // Компоненты не регистрируются вручную: Yoyo сам разрешает
            // kebab-case имя в класс LaIOT\Yoyo\StudlyCase по namespace.
            $yoyo->registerViewProvider(static fn (): BladeViewProvider => new BladeViewProvider($bladeView));

            return $yoyo;
        });
    }

    public function boot(): void
    {
        // Yoyo должен знать адаптер и компоненты до boot модулей и рендера.
        $this->container->make(Yoyo::class);
    }

    private function views(): string
    {
        return (string)$this->app->config('views', $this->app->root() . '/resources/views');
    }
}
