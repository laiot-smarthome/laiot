<?php

declare(strict_types=1);

namespace LaIOT\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Минимальный middleware для проверки конвейера.
 *
 * Request id выставляет kernel; middleware только подтверждает, что запрос
 * действительно прошёл через цепочку маршрута.
 */
final class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response instanceof Response) {
            $response->headers->set('X-LaIOT-Middleware', 'yes');
        }

        return $response;
    }
}
