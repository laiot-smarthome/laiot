<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\Session\SessionStore;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Первая новая страница ядра: статус системы в alpha-layout.
 *
 * Она не относится к модулям и показывает, что новый kernel, сервисы и
 * layouts.alpha работают вместе без отдельного нового дизайна.
 */
final readonly class StatusController extends Controller
{
    public function __construct(
        private Factory $view,
        private Application $app,
        private SessionStore $session
    ) {
    }

    public function __invoke(): Response
    {
        $databaseOk = $this->app->database()->ping();
        try {
            $this->app->cache()->put('status.page', 'ok', 30);
            $cacheOk = $this->app->cache()->get('status.page') === 'ok';
            $this->app->cache()->forget('status.page');
        } catch (Throwable) {
            $cacheOk = false;
        }

        $content = $this->view->make('pages.status', [
            'version' => (string)$this->app->config('version', 'dev'),
            'php' => PHP_VERSION,
            'databaseOk' => $databaseOk,
            'cacheOk' => $cacheOk,
            'modules' => count($this->app->modules()->all()),
        ])->render();

        return $this->html($this->view->make('layouts.alpha', [
            'title' => 'Статус системы',
            'content' => $content,
            'active' => 'status',
            'csrf' => $this->session->csrfToken(),
            'user' => $this->session->user(),
            'menu' => [[
                'label' => 'Система',
                'items' => [
                    ['id' => 'status', 'label' => 'Статус', 'href' => '/status', 'icon' => 'ti-activity'],
                    ['id' => 'shoutbox', 'label' => 'Shoutbox', 'href' => '/module/shoutbox', 'icon' => 'ti-message'],
                ],
            ]],
        ])->render());
    }
}
