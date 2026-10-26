<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Http\JsonResponse;
use MajorDoMo\Cache\CacheStore;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Http\Controller;
use Throwable;

/**
 * Проверка готовности: приложение, БД и кэш.
 *
 * 503 означает «сервис ещё не готов», а не «страница не найдена».
 */
final readonly class HealthController extends Controller
{
    public function __construct(
        private DatabaseManager $database,
        private CacheStore $cache
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $database = 'ok';
        if (!$this->database->isConfigured()) {
            $database = 'not_configured';
        } elseif (!$this->database->ping()) {
            $database = 'unavailable';
        }

        $cache = 'ok';
        try {
            $key = 'health.' . bin2hex(random_bytes(6));
            $this->cache->put($key, 'ok', 30);
            if ($this->cache->get($key) !== 'ok') {
                $cache = 'failed';
            }
            $this->cache->forget($key);
        } catch (Throwable) {
            $cache = 'unavailable';
        }

        $ok = $database === 'ok' && $cache === 'ok';

        return $this->json([
            'status' => $ok ? 'ok' : 'degraded',
            'app' => 'MyDoMo',
            'database' => $database,
            'cache' => $cache,
        ], $ok ? 200 : 503);
    }
}
