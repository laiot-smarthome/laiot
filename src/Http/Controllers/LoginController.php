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
 * Новая таблица users MyDoMo, password_hash/password_verify и отдельная
 * сессия `mydomo`. Форма защищена CSRF, пароль не логируется.
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
            return new RedirectResponse('/admin');
        }

        return $this->render(null, 200, '');
    }

    public function login(Request $request): Response
    {
        $username = trim((string)$request->request->get('username', ''));
        $password = (string)$request->request->get('password', '');
        $user = $this->authenticator->attempt($username, $password);

        if ($user === null) {
            return $this->render('Неверный логин или пароль.', 422, $username);
        }

        $this->session->login($user);

        return new RedirectResponse('/admin');
    }

    public function logout(): Response
    {
        $this->session->logout();

        return new RedirectResponse('/login');
    }

    private function render(?string $error, int $status, string $username): Response
    {
        $html = $this->view->make('login', [
            'csrf' => $this->session->csrfToken(),
            'error' => $error,
            'username' => $username,
        ])->render();

        return $this->html($html, $status);
    }
}
