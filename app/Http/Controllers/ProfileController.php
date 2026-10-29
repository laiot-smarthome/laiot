<?php

declare(strict_types=1);

namespace LaIOT\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LaIOT\Http\Controller;
use LaIOT\Http\PanelPage;
use LaIOT\Http\PanelRenderer;
use LaIOT\Http\Session\SessionStore;
use LaIOT\Services\Auth\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Профиль текущего пользователя: имя и смена пароля.
 *
 * Изменения:
 * 2026-10-26 — смена своего пароля обновляет отпечаток текущей сессии.
 */
final readonly class ProfileController extends Controller
{
    public function __construct(
        private Factory $view,
        private UserRepository $users,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function show(): Response
    {
        return $this->render();
    }

    public function save(Request $request): Response
    {
        $user = $this->session->user();
        if ($user === null) {
            return new RedirectResponse('/login');
        }

        $name = trim((string)$request->request->get('name', ''));
        $password = (string)$request->request->get('password', '');

        try {
            $this->users->changeProfile((int)$user['id'], $name, $password !== '' ? $password : null);
        } catch (InvalidArgumentException $error) {
            return $this->render($error->getMessage(), $name, 422);
        }

        $current = $this->users->sessionUser((int)$user['id']);
        if ($current === null) {
            $this->session->logout();

            return new RedirectResponse('/login');
        }
        $this->session->login($current);

        return new RedirectResponse('/admin/profile');
    }

    private function render(?string $error = null, ?string $name = null, int $status = 200): Response
    {
        $user = $this->session->user() ?? [];
        $content = $this->view->make('pages.profile', [
            'user' => $user,
            'name' => $name ?? (string)($user['name'] ?? ''),
            'csrf' => $this->session->csrfToken(),
            'error' => $error,
        ])->render();

        return $this->panel->render(new PanelPage('Профиль', $content, 'profile'), $status);
    }
}
