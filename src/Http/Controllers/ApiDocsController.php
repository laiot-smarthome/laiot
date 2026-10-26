<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Routing\Router;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Живой список API-маршрутов из Router.
 */
final readonly class ApiDocsController extends Controller
{
    public function __construct(
        private Factory $view,
        private Router $router,
        private PanelRenderer $panel
    ) {
    }

    public function __invoke(): Response
    {
        $routes = [];
        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $uri = '/' . ltrim((string)$route->uri(), '/');
            if (!str_starts_with($uri, '/api/')) {
                continue;
            }
            $routes[] = [
                'methods' => implode(', ', $route->methods()),
                'uri' => $uri,
                'name' => (string)($route->getName() ?? ''),
            ];
        }
        usort($routes, static fn (array $a, array $b): int => strcmp($a['uri'], $b['uri']));

        $content = $this->view->make('pages.api', ['routes' => $routes])->render();

        return $this->panel->render(new PanelPage('API', $content, 'api'));
    }
}
