<?php

declare(strict_types=1);

namespace LaIOT\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use LaIOT\Foundation\Application;
use LaIOT\Http\Controller;
use LaIOT\Http\PanelPage;
use LaIOT\Http\PanelRenderer;
use LaIOT\Services\Panel\DashboardStats;
use LaIOT\Support\MaintenanceMode;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Веб-админка: обзор состояния дома на Blade-компонентах внутри alpha-layout.
 *
 * 2026-10-26 — данные собирает DashboardStats, страница показывает реальные
 * счётчики, журнал изменений свойств, очередь и ошибки. Циклы и фоновые
 * процессы здесь не запускаются: обзор только читает состояние.
 */
final readonly class DashboardController extends Controller
{
    public function __construct(
        private Factory $view,
        private Application $app,
        private PanelRenderer $panel,
        private DashboardStats $stats,
        private MaintenanceMode $maintenance
    ) {
    }

    public function __invoke(): Response
    {
        $databaseOk = $this->app->database()->ping();
        try {
            $this->app->cache()->put('dashboard.ping', 'ok', 30);
            $cacheOk = $this->app->cache()->get('dashboard.ping') === 'ok';
        } catch (Throwable) {
            $cacheOk = false;
        }

        $content = $this->view->make('pages.dashboard', [
            'version' => (string)$this->app->config('version', 'dev'),
            'php' => PHP_VERSION,
            'databaseOk' => $databaseOk,
            'cacheOk' => $cacheOk,
            'maintenance' => $this->maintenance->enabled(),
            'timezone' => $this->app->clock()->timezone(),
            'now' => $this->app->clock()->now(),
            'counts' => $this->stats->counts(),
            'queue' => $this->stats->queue(),
            'events' => $this->stats->recentEvents(8),
            'errors' => $this->stats->recentErrors(5),
            'actions' => $this->stats->recentActions(6),
        ])->render();

        return $this->panel->render(new PanelPage('Обзор', $content, 'dashboard'));
    }
}
