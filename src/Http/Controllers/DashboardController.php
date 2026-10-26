<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Новая веб-админка: обзор на Blade-компонентах внутри alpha-layout.
 *
 * Циклы и фоновые процессы здесь не запускаются — только чтение состояния.
 */
final readonly class DashboardController extends Controller
{
    public function __construct(
        private Factory $view,
        private Application $app,
        private PanelRenderer $panel
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
            'modules' => $this->app->modules()->all(),
            'timezone' => $this->app->clock()->timezone(),
        ])->render();

        return $this->panel->render(new PanelPage('Обзор', $content, 'dashboard'));
    }
}
