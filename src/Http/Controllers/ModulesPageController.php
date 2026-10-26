<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Modules\ModuleManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Реестр core-модулей: версии, таблицы, helpers, маршрут.
 */
final readonly class ModulesPageController extends Controller
{
    public function __construct(
        private Factory $view,
        private ModuleManager $modules,
        private PanelRenderer $panel
    ) {
    }

    public function __invoke(): Response
    {
        $content = $this->view->make('pages.modules', [
            'modules' => $this->modules->all(),
        ])->render();

        return $this->panel->render(new PanelPage('Модули', $content, 'modules'));
    }
}
