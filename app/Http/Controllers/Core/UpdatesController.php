<?php

declare(strict_types=1);

namespace LaIOT\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use LaIOT\Http\Controller;
use LaIOT\Http\PanelPage;
use LaIOT\Http\PanelRenderer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ядровая страница обновлений; проверки и действия выполняет Yoyo-компонент.
 * 2026-10-29 — версия, каналы и переход к независимому прогрессу.
 */
final readonly class UpdatesController extends Controller
{
    public function __construct(private Factory $view, private PanelRenderer $panel)
    {
    }

    public function index(): Response
    {
        return $this->panel->render(new PanelPage('Обновление системы', $this->view->make('pages.updates')->render(), 'updates'));
    }
}
