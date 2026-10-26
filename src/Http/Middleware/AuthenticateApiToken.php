<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use MajorDoMo\Auth\ApiTokenRepository;
use MajorDoMo\Http\ErrorResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer/X-API-Key для /api/v1. Токен ищется только в хеше БД.
 */
final readonly class AuthenticateApiToken
{
    public function __construct(private ApiTokenRepository $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->token($request);
        $userId = $token !== null ? $this->tokens->userId($token) : null;
        if ($userId === null) {
            return ErrorResponse::make(
                $request,
                401,
                'unauthorized',
                'Unauthorized',
                (string)$request->attributes->get('request_id', '')
            );
        }

        $request->attributes->set('api_user_id', $userId);

        return $next($request);
    }

    private function token(Request $request): ?string
    {
        $header = trim((string)$request->headers->get('Authorization', ''));
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches) === 1) {
            return trim($matches[1]);
        }

        $key = trim((string)$request->headers->get('X-API-Key', ''));

        return $key !== '' ? $key : null;
    }
}
