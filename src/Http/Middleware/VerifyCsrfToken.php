<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MajorDoMo\Http\Session\SessionStore;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSRF для изменяющих методов. Токен принимается из заголовка
 * X-CSRF-Token или поля _token.
 */
final readonly class VerifyCsrfToken
{
    public function __construct(private SessionStore $session)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $token = $request->headers->get('X-CSRF-Token') ?? $request->request->get('_token');
        if (!$this->session->checkCsrf($token)) {
            return new JsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'csrf_failed',
                    'message' => 'Запрос отклонён. Обновите страницу.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
