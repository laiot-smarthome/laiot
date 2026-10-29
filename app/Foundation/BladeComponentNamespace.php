<?php

declare(strict_types=1);

namespace LaIOT\Foundation;

/**
 * Минимальный адаптер Foundation\Application для Blade-компилятора.
 *
 * 2026-10-27 — Illuminate\View при компиляции нового тега `<x-...>`
 * спрашивает у Foundation\Application пространство имён компонентов.
 * Полного Laravel в LaIOT нет, поэтому контейнеру отдаётся только нужный
 * метод, а не весь контракт.
 */
final readonly class BladeComponentNamespace
{
    public function __construct(private string $namespace)
    {
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }
}
