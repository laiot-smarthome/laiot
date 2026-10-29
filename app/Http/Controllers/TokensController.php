<?php

declare(strict_types=1);

namespace LaIOT\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use LaIOT\Http\Controller;
use LaIOT\Http\PanelPage;
use LaIOT\Http\PanelRenderer;
use LaIOT\Http\Session\SessionStore;
use LaIOT\Services\Auth\ApiTokenRepository;
use LaIOT\Services\Auth\UserRepository;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * API-токены в веб-админке: выпуск, список, отзыв.
 */
final readonly class TokensController extends Controller
{
    public function __construct(
        private Factory $view,
        private ApiTokenRepository $tokens,
        private UserRepository $users,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(): Response
    {
        return $this->render();
    }

    public function issue(Request $request): Response
    {
        $userId = (int)$request->request->get('user_id', 0);
        $name = trim((string)$request->request->get('name', ''));
        $days = (int)$request->request->get('days', 0);

        try {
            $token = $this->tokens->issue($userId, $name, $days > 0 ? $days * 86400 : null);
        } catch (RuntimeException $error) {
            return $this->render($error->getMessage(), null, 422);
        }

        return $this->render(null, $token);
    }

    public function revoke(string $id): Response
    {
        $this->tokens->revoke((int)$id);

        return new RedirectResponse('/admin/tokens');
    }

    private function render(?string $error = null, ?string $issued = null, int $status = 200): Response
    {
        $content = $this->view->make('pages.tokens', [
            'tokens' => $this->tokens->all(),
            'users' => $this->users->all(),
            'csrf' => $this->session->csrfToken(),
            'error' => $error,
            'issued' => $issued,
        ])->render();

        return $this->panel->render(new PanelPage('API-токены', $content, 'tokens'), $status);
    }
}
