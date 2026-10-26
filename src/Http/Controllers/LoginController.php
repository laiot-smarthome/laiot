<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Audit\ActionLogger;
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
        private AdminAuthenticator $authenticator,
        private ActionLogger $audit
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
            $this->audit->log('login.failed', 'Неудачный вход: ' . $username, $username, 'web', (string)$request->getClientIp());

            return $this->render('Неверный логин или пароль.', 422, $username);
        }

        $this->session->login($user);
        $this->audit->log('login.success', 'Вход: ' . $username, $username, 'web', (string)$request->getClientIp());

        return new RedirectResponse('/admin');
    }

    public function logout(): Response
    {
        $user = $this->session->user();
        if ($user !== null) {
            $this->audit->log('logout', 'Выход: ' . $user['username'], (string)$user['username']);
        }
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
