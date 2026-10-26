<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Http\JsonResponse;
use MajorDoMo\Database\SettingsRepository;
use MajorDoMo\Http\Controller;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Чтение настройки через репозиторий. Это проверка связки HTTP → сервис →
 * Eloquent, а не новый публичный API: маршрут временный, для stage 1.
 */
final readonly class SettingController extends Controller
{
    public function __construct(
        private SettingsRepository $settings,
        private LoggerInterface $logger
    ) {
    }

    public function show(string $name): JsonResponse
    {
        try {
            return $this->json([
                'name' => $name,
                'value' => $this->settings->get($name),
            ]);
        } catch (Throwable $error) {
            $this->logger->error('Cannot read setting', [
                'name' => $name,
                'exception' => $error,
            ]);

            return $this->json([
                'name' => $name,
                'error' => 'database_unavailable',
            ], 503);
        }
    }
}
