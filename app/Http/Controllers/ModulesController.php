<?php

declare(strict_types=1);

namespace LaIOT\Http\Controllers;

use LaIOT\Http\Controller;
use LaIOT\Services\Modules\ModuleManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Публичный список core-модулей для API и диагностики.
 */
final readonly class ModulesController extends Controller
{
    public function __construct(private ModuleManager $modules)
    {
    }

    public function __invoke(): Response
    {
        $rows = [];
        foreach ($this->modules->all() as $module) {
            $rows[] = [
                'id' => (string)$module['id'],
                'type' => (string)($module['type'] ?? 'core'),
                'name' => (string)($module['name'] ?? $module['id']),
                'version' => (string)($module['version'] ?? '0.0.0'),
            ];
        }

        return $this->json(['modules' => $rows]);
    }
}
