<?php

declare(strict_types=1);

namespace LaIOT\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LaIOT\Http\ErrorResponse;
use LaIOT\Services\Auth\Authorization;
use Symfony\Component\HttpFoundation\Response;

/**
 * Проверка одного или нескольких permissions на маршруте.
 * Пример: ->middleware('can:panel.view').
 */
final readonly class Authorize
{
    public function __construct(private Authorization $authorization)
    {
    }

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        foreach ($permissions as $permission) {
            if (!$this->authorization->can($permission)) {
                return ErrorResponse::make(
                    $request,
                    403,
                    'forbidden',
                    'Forbidden',
                    (string)$request->attributes->get('request_id', '')
                );
            }
        }

        return $next($request);
    }
}
