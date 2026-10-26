<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Http\Request;
use MajorDoMo\Http\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Минимальный защищённый API-эндпоинт для проверки токена.
 */
final readonly class ApiMeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return $this->json([
            'user_id' => (int)$request->attributes->get('api_user_id', 0),
        ]);
    }
}
