<?php

declare(strict_types=1);

namespace LaIOT\View;

use Illuminate\Container\Container;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\ViewFinderInterface;

/**
 * Адаптер между нашим Blade Factory и Yoyo.
 *
 * Yoyo ожидает объект с getContainer(), make(), exists(), getFinder() и
 * getEngineResolver(). Напрямую Illuminate\View\Factory этого не даёт,
 * поэтому оборачиваем его, не дублируя рендеринг.
 */
final readonly class BladeView
{
    public function __construct(
        private Factory $factory,
        private Container $container
    ) {
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $mergeData
     */
    public function make(string $view, array $data = [], array $mergeData = []): \Illuminate\Contracts\View\View
    {
        return $this->factory->make($view, $data, $mergeData);
    }

    public function exists(string $view): bool
    {
        return $this->factory->exists($view);
    }

    public function getFinder(): ViewFinderInterface
    {
        return $this->factory->getFinder();
    }

    public function getEngineResolver(): EngineResolver
    {
        return $this->factory->getEngineResolver();
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->factory->{$method}(...$arguments);
    }
}
