<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Http\JsonResponse;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Http\Controller;

/**
 * Проверка готовности: приложение, БД и версия.
 *
 * 503 означает «сервис ещё не готов», а не «страница не найдена».
 */
final readonly class HealthController extends Controller
{
    public function __construct(private DatabaseManager $database)
    {
    }

    public function __invoke(): JsonResponse
    {
        if (!$this->database->isConfigured()) {
            return $this->json([
                'status' => 'degraded',
                'app' => 'MyDoMo',
                'database' => 'not_configured',
            ], 503);
        }

        if (!$this->database->ping()) {
            return $this->json([
                'status' => 'degraded',
                'app' => 'MyDoMo',
                'database' => 'unavailable',
            ], 503);
        }

        return $this->json([
            'status' => 'ok',
            'app' => 'MyDoMo',
            'database' => 'ok',
        ]);
    }
}
