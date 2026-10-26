<?php

declare(strict_types=1);

namespace MajorDoMo\View;

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

        $files = new Filesystem();
        $compiler = new BladeCompiler($files, $cache);
        $resolver = new EngineResolver();
        $resolver->register('blade', static fn (): CompilerEngine => new CompilerEngine($compiler, $files));

        $this->factory = new Factory(
            $resolver,
            new FileViewFinder($files, [$views]),
            new Dispatcher()
        );

        $container = new Container();
        Container::setInstance($container);
        $container->instance(Factory::class, $this->factory);
        $container->instance(ViewFactory::class, $this->factory);
        $container->instance('view', $this->factory);
        $this->factory->setContainer($container);
        BladeComponents::register($compiler, $views);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        return $this->factory->make($view, $data)->render();
    }
}
