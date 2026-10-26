<?php

declare(strict_types=1);

namespace MajorDoMo\Modules\Core\Shoutbox;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shoutbox как core-модуль: ModuleDb, Blade-страница, alpha-layout, CSRF.
 */
final readonly class ShoutboxController extends Controller
{
    public function __construct(
        private Factory $view,
        private ShoutRepository $shouts,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(): Response
    {
        return $this->render();
    }

    public function store(Request $request): Response
    {
        $message = trim((string)$request->request->get('message', ''));

        if ($message === '' || mb_strlen($message) > 255) {
            return $this->render('Введите сообщение до 255 символов.', 422);
        }

        $user = $this->session->user();
        $this->shouts->create($message, (int)($user['id'] ?? 0));

        return new RedirectResponse('/module/shoutbox');
    }

    public function delete(string $id): Response
    {
        $this->shouts->delete((int)$id);

        return new RedirectResponse('/module/shoutbox');
    }

    private function render(?string $error = null, int $status = 200): Response
    {
        $content = $this->view->make('shoutbox::index', [
            'messages' => $this->shouts->latest(),
            'csrf' => $this->session->csrfToken(),
            'error' => $error,
        ])->render();

        return $this->panel->render(new PanelPage('Shoutbox', $content, 'shoutbox'), $status);
    }
}
