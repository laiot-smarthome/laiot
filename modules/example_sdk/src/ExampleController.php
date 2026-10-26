<?php

declare(strict_types=1);

namespace Example\Sdk;

use Illuminate\Contracts\View\Factory;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use Symfony\Component\HttpFoundation\Response;

final readonly class ExampleController extends Controller
{
    public function __construct(
        private Factory $view,
        private PanelRenderer $panel
    ) {
    }

    public function __invoke(): Response
    {
        $content = $this->view->make('example_sdk::index')->render();

        return $this->panel->render(new PanelPage('SDK Example', $content, 'example_sdk'));
    }
}
