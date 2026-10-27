<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Оболочка редактора окружения; интерактив и сохранение выполняет Yoyo.
 *
 * 2026-10-27 — системные настройки переведены с таблицы settings на .env.
 */
final readonly class SettingsController extends Controller
{
    public function __construct(private Factory $view, private PanelRenderer $panel)
    {
    }

    public function index(Request $request): Response
    {
        $content = $this->view->make('pages.settings.index', [
            'group' => (string)$request->query->get('group', 'general'),
            'search' => trim((string)$request->query->get('q', '')),
        ])->render();

        return $this->panel->render(new PanelPage('Настройки', $content, 'settings'));
    }
}
