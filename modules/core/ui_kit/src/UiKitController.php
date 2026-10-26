<?php

declare(strict_types=1);

namespace MajorDoMo\Modules\Core\UiKit;

use Illuminate\Contracts\View\Factory;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Живой справочник alpha-компонентов MyDoMo.
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
        $content = $this->view->make('ui_kit::index')->render();

        return $this->panel->render(new PanelPage('UI Kit', $content, 'ui_kit'));
    }
}
