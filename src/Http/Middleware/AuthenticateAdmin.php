<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MajorDoMo\Http\Session\SessionStore;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Требует вход администратора. AJAX/htmx получает JSON 401, страница —
 * редирект на /login.
 */
final readonly class AuthenticateAdmin
{
    public function __construct(private SessionStore $session)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->session->user() !== null) {
            return $next($request);
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
