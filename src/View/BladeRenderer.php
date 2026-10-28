<?php

declare(strict_types=1);

namespace MajorDoMo\View;

use Clickfwd\Yoyo\Blade\YoyoBladeCompilerEngine;
use Clickfwd\Yoyo\Blade\YoyoBladeDirectives;
use Clickfwd\Yoyo\Services\Configuration;
use Clickfwd\Yoyo\ViewProviders\BladeViewProvider;
use Clickfwd\Yoyo\Yoyo;
use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use RuntimeException;

/**
 * Временный standalone-рендерер Blade для перехода старых страниц админки
 * на новые представления.
 *
 * Старый alpha-shell пока не поднимает новый Application, поэтому UI Kit
 * может отрендерить Blade-секцию через этот мост. Когда shell переедет на
 * новый kernel, мост удаляется.
 *
 * 2026-10-28 — адаптер Yoyo позволяет показать редактор связей без ядра и БД.
 * 2026-10-27 — мост настраивает Yoyo и регистрирует его Blade-директивы:
 * `@yoyo_scripts` в общем layout без этого оставался текстом в HTML, а
 * страница с кэшированным шаблоном падала на пустой конфигурации.
 */
final class BladeRenderer
{
    private readonly Factory $factory;

    public function __construct(private readonly string $root)
    {
        $views = $this->root . '/resources/views';
        $cache = $this->root . '/var/cache/views';
        if (!is_dir($cache) && !mkdir($cache, 0775, true) && !is_dir($cache)) {
            throw new RuntimeException('Не удалось создать кэш Blade.');
        }

        // Те же параметры, что у ViewServiceProvider: bridge не поднимает
        // провайдеры, но layout обязан получить рабочий `@yoyo_scripts`.
        Configuration::getInstance([
            'url' => '/yoyo',
            'scriptsPath' => '/assets/vendor/yoyo',
            'namespace' => 'MajorDoMo\\Yoyo\\',
            'htmx' => '/assets/vendor/htmx/htmx.min.js',
        ]);

        $files = new Filesystem();
        $compiler = new BladeCompiler($files, $cache);
        // Без регистрации директив `@yoyo_scripts` оставался в HTML текстом:
        // в новом kernel это делает YoyoBladeCompilerEngine, у bridge его нет.
        new YoyoBladeDirectives($compiler);
        $resolver = new EngineResolver();
        $resolver->register('blade', static fn (): CompilerEngine => new YoyoBladeCompilerEngine($compiler, $files));

        $this->factory = new Factory(
            $resolver,
            new FileViewFinder($files, [$views, $views . '/yoyo']),
            new Dispatcher()
        );

        $container = new Container();
        Container::setInstance($container);
        $container->instance(Factory::class, $this->factory);
        $container->instance(ViewFactory::class, $this->factory);
        $container->instance('view', $this->factory);
        $container->instance('view.engine.resolver', $resolver);
        $this->factory->setContainer($container);
        BladeComponents::register($compiler, $views);
        $bladeView = new BladeView($this->factory, $container);
        (new Yoyo())->registerViewProvider(static fn (): BladeViewProvider => new BladeViewProvider($bladeView));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        return $this->factory->make($view, $data)->render();
    }
}
