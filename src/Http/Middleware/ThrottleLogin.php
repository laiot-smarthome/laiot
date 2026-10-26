<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use MajorDoMo\Cache\CacheStore;
use MajorDoMo\Http\ErrorResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Простой лимит попыток входа по IP через CacheStore.
 *
 * После успешного входа (302 на /admin) счётчик сбрасывается. Ошибка
 * валидации/пароля оставляет попытку, чтобы перебор не был бесплатным.
 *
 * Изменения:
 * 2026-10-26 — успешный вход сбрасывает лимит по актуальному адресу панели.
 */
final readonly class ThrottleLogin
{
    public function __construct(private CacheStore $cache)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $key = 'login.' . sha1((string)$request->getClientIp());
        $attempts = (int)$this->cache->get($key, 0);
        if ($attempts >= 5) {
            return ErrorResponse::make(
                $request,
                429,
                'too_many_requests',
                'Too Many Requests',
                (string)$request->attributes->get('request_id', '')
            );
        }
        $this->cache->put($key, $attempts + 1, 60);

        $response = $next($request);
        if ($response->getStatusCode() === 302 && $response->headers->get('Location') === '/admin') {
            $this->cache->forget($key);
        }

        return $response;
    }
}
