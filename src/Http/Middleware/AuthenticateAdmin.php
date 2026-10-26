<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MajorDoMo\Auth\UserRepository;
use MajorDoMo\Http\Session\SessionStore;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Требует вход администратора. AJAX/htmx получает JSON 401, страница —
 * редирект на /login.
 *
 * Изменения:
 * 2026-10-26 — удаление, снятие прав и смена пароля отзывают старую сессию.
 */
final readonly class AuthenticateAdmin
{
    public function __construct(
        private SessionStore $session,
        private UserRepository $users
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $stored = $this->session->user();
        if ($stored !== null) {
            $current = $this->users->sessionUser($stored['id']);
            if ($current !== null && is_string($stored['stamp'] ?? null)
                && hash_equals($current['stamp'], $stored['stamp'])) {
                return $next($request);
            }
            $this->session->logout();
        }

        if ($request->expectsJson() || $request->headers->get('HX-Request') === 'true' || $request->getMethod() !== 'GET') {
            return new JsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'authentication_required',
                    'message' => 'Необходимо войти в систему.',
                ],
            ], 401);
        }

        return new RedirectResponse('/login');
    }
}
