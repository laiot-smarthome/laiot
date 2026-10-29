<?php

declare(strict_types=1);

namespace LaIOT\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use LaIOT\Http\Controller;
use LaIOT\Http\PanelPage;
use LaIOT\Http\PanelRenderer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Живой справочник alpha-компонентов LaIOT.
 */
final readonly class UiKitController extends Controller
{
    public function __construct(
        private Factory $view,
        private PanelRenderer $panel
    ) {
    }

    public function index(): Response
    {
        $content = $this->view->make('pages.ui_kit.index')->render();

        return $this->panel->render(new PanelPage('UI Kit', $content, 'ui_kit'));
    }
}
