<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Auth\AdminAuthenticator;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\Session\SessionStore;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Минимальный вход администратора для нового ядра.
 *
 * Использует ту же таблицу users и формат паролей, что alpha, но отдельную
 * сессию `mydomo`. Форма защищена CSRF, пароль не логируется.
 */
final readonly class LoginController extends Controller
{
    public function __construct(
        private Factory $view,
        private SessionStore $session,
        private AdminAuthenticator $authenticator
    ) {
    }

    public function show(): Response
    {
        if ($this->session->user() !== null) {
            return new RedirectResponse('/yoyo-demo');
        }

        return $this->render(null, 200);
    }

    public function login(Request $request): Response
    {
        $username = trim((string)$request->request->get('username', ''));
        $password = (string)$request->request->get('password', '');
        $user = $this->authenticator->attempt($username, $password);

        if ($user === null) {
            return $this->render('Неверный логин или пароль.', 422);
        }

        $this->session->login($user);

        return new RedirectResponse('/yoyo-demo');
    }

    public function logout(): Response
    {
        $this->session->logout();

        return new RedirectResponse('/login');
    }

    private function render(?string $error, int $status): Response
    {
        $html = $this->view->make('login', [
            'csrf' => $this->session->csrfToken(),
            'error' => $error,
        ])->render();

        return $this->html($html, $status);
    }
}
