<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Публичная информация о сборке для API и диагностики.
 */
final readonly class AboutController extends Controller
{
    public function __construct(private Application $app)
    {
    }

    public function __invoke(): Response
    {
        return $this->json([
            'app' => 'MyDoMo',
            'version' => (string)$this->app->config('version', 'dev'),
            'php' => PHP_VERSION,
            'core_modules' => count($this->app->modules()->all()),
        ]);
    }
}
