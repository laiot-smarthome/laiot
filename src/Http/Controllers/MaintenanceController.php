<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Support\MaintenanceMode;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Управление режимом обслуживания из админки.
 */
final readonly class MaintenanceController extends Controller
{
    public function __construct(
        private Factory $view,
        private MaintenanceMode $mode,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function show(): Response
    {
        $content = $this->view->make('pages.maintenance', [
            'enabled' => $this->mode->enabled(),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Обслуживание', $content, 'maintenance'));
    }

    public function enable(): Response
    {
        $this->mode->enable();

        return new RedirectResponse('/admin/maintenance');
    }

    public function disable(): Response
    {
        $this->mode->disable();

        return new RedirectResponse('/admin/maintenance');
    }
}
