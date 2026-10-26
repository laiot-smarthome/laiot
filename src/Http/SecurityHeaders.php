<?php

declare(strict_types=1);

namespace MajorDoMo\Http;

use Symfony\Component\HttpFoundation\Response;

/**
 * Базовые security-заголовки для ответов нового ядра.
 *
 * CSP пока не ставим: alpha-shell использует inline-скрипты и сторонние
 * ассеты. Эти заголовки безопасны и не меняют поведение страниц.
 */
final class SecurityHeaders
{
    public static function apply(Response $response): void
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];

        foreach ($headers as $name => $value) {
            if (!$response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }
    }
}
