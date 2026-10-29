<?php

declare(strict_types=1);

namespace LaIOT\View;

use Illuminate\View\Compilers\BladeCompiler;

/**
 * Регистрация alpha-компонентов `x-md.*`.
 *
 * Один список и для нового kernel, и для временного BladeRenderer: иначе
 * компонент работает в тесте, но падает на живой странице.
 *
 * 2026-10-28 — зарегистрированы ссылка без подчёркивания и её варианты.
 * 2026-10-28 — Notify доступен в kernel и standalone-рендерере.
 * 2026-10-28 — зарегистрированы лоадер, маскот и его пустое состояние.
 * 2026-10-27 — зарегистрированы типографика и хлебные крошки.
 * 2026-10-27 — зарегистрированы дерево объектов и просмотр JSON.
 * 2026-10-27 — зарегистрирован календарь и выбор времени.
 * 2026-10-27 — добавлены компоненты форм, таблиц и виджетов UI Kit.
 */
final class BladeComponents
{
    /** @var list<string> */
    public const COMPONENTS = [
        'activity-feed',
        'automation-row',
        'alert',
        'avatar',
        'avatar-group',
        'badge',
        'breadcrumbs',
        'button',
        'button-group',
        'card',
        'checkbox',
        'choice-group',
        'chart-card',
        'code',
        'code-sample',
        'command-palette',
        'connection-status',
        'context-menu',
        'call-flow',
        'call-flow-nodes',
        'cycle-card',
        'disclosure',
        'data-table',
        'date-time-picker',
        'device-card',
        'drawer',
        'dropdown',
        'empty-state',
        'energy-widget',
        'entity',
        'entity-text',
        'field',
        'file-upload',
        'hint',
        'history-drawer',
        'icon',
        'icon-button',
        'icon-browser',
        'input',
        'json-view',
        'kpi',
        'last-seen',
        'linked-object',
        'light-control',
        'link',
        'logo',
        'loader',
        'mascot',
        'mascot-empty',
        'meter',
        'modal',
        'notify',
        'object-tree',
        'page-hero',
        'progress',
        'radio',
        'range',
        'room-card',
        'scene-card',
        'section-heading',
        'select',
        'sensor',
        'skeleton',
        'sparkline',
        'status',
        'switch',
        'table',
        'tabs',
        'textarea',
        'thermostat',
        'tooltip',
        'toasts',
        'trace-drawer',
        'typography',
    ];

    public static function register(BladeCompiler $compiler, string $views): void
    {
        $compiler->anonymousComponentPath($views . '/components');
        foreach (self::COMPONENTS as $component) {
            $compiler->component('components.md.' . $component, 'md.' . $component);
        }
    }
}
