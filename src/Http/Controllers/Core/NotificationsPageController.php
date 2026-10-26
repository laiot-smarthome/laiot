<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Notifications\NotificationService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Страница оповещений: список непрочитанных и явное прочтение.
 */
final readonly class NotificationsPageController extends Controller
{
    public function __construct(
        private Factory $view,
        private PanelRenderer $panel,
        private NotificationService $notifications,
        private SessionStore $session
    ) {
    }

    public function index(): Response
    {
        $content = $this->view->make('pages.notifications.index', [
            'notifications' => $this->notifications->listing(),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Оповещения', $content, 'notifications'));
    }

    public function dismiss(int $id): Response
    {
        $this->notifications->dismiss($id);

        return new RedirectResponse('/notifications');
    }
}
