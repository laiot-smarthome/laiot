<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Database\SettingsRepository;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Настройки MyDoMo на Blade.
 *
 * Таблица `settings` остаётся legacy alpha (uppercase-колонки); доступ идёт
 * только через SettingsRepository, без SQL в контроллере.
 */
final readonly class SettingsController extends Controller
{
    public function __construct(
        private Factory $view,
        private SettingsRepository $settings,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $content = $this->view->make('pages.settings.index', [
            'settings' => $this->settings->details($search),
            'search' => $search,
        ])->render();

        return $this->panel->render(new PanelPage('Настройки', $content, 'settings'));
    }

    public function edit(string $id): Response
    {
        $setting = $this->settings->find((int)$id);
        if ($setting === null) {
            return $this->panel->render(new PanelPage('Настройки', '<p class="md-alert md-alert--danger">Настройка не найдена.</p>', 'settings'), 404);
        }

        $content = $this->view->make('pages.settings.edit', [
            'setting' => $setting,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Настройки', $content, 'settings'));
    }

    public function save(string $id, Request $request): Response
    {
        $this->settings->save((int)$id, (string)$request->request->get('value', ''));

        return new RedirectResponse('/module/settings');
    }
}
