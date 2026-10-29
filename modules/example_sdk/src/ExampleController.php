<?php

declare(strict_types=1);

namespace Example\Sdk;

use Illuminate\Contracts\View\Factory;
use LaIOT\Http\Controller;
use LaIOT\Http\PanelPage;
use LaIOT\Http\PanelRenderer;
use LaIOT\Services\Modules\ModuleManager;
use Symfony\Component\HttpFoundation\Response;

final readonly class ExampleController extends Controller
{
    public function __construct(
        private Factory $view,
        private PanelRenderer $panel,
        private ModuleManager $modules
    ) {
    }

    public function __invoke(): Response
    {
        $context = $this->modules->context('example_sdk');
        $content = $this->view->make('example_sdk::index', [
            'settings' => $context->settings()->all(),
            'subscriptions' => $context->subscriptions()->attached(),
        ])->render();

        return $this->panel->render(new PanelPage('SDK Example', $content, 'example_sdk'));
    }
}
