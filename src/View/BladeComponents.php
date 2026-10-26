<?php

declare(strict_types=1);

namespace MajorDoMo\View;

use Illuminate\View\Compilers\BladeCompiler;

/**
 * Регистрация alpha-компонентов `x-md.*`.
 *
 * Один список и для нового kernel, и для временного BladeRenderer: иначе
 * компонент работает в тесте, но падает на живой странице.
 */
final class BladeComponents
{
    /** @var list<string> */
    public const COMPONENTS = [
        'activity-feed',
        'alert',
        'avatar',
        'badge',
        'button',
        'card',
        'checkbox',
        'code',
        'disclosure',
        'drawer',
        'empty-state',
        'field',
        'hint',
        'icon',
        'input',
        'kpi',
        'modal',
        'progress',
        'section-heading',
        'select',
        'skeleton',
        'status',
        'switch',
        'textarea',
    ];

    public static function register(BladeCompiler $compiler, string $views): void
    {
        $compiler->anonymousComponentPath($views . '/components');
        foreach (self::COMPONENTS as $component) {
            $compiler->component('components.md.' . $component, 'md.' . $component);
        }
    }
}
