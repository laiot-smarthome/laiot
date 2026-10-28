<?php

declare(strict_types=1);

namespace MajorDoMo\Mqtt\Http;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Mqtt\RuntimeState;
use MajorDoMo\Mqtt\Settings;
use MajorDoMo\Mqtt\TopicService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Настройки брокера MQTT.
 *
 * Core-модуль не использует общий экран настроек SDK, поэтому форма своя,
 * но в том же дизайне `md-card`/`md-field`. После сохранения цикл приёма
 * переподключается сам (версия настроек в кэше).
 *
 * 2026-10-28 — результаты операций передаются Notify без текста в URL.
 * 2026-10-27 — страница настроек MQTT.
 */
final readonly class SettingsController extends Controller
{
    public function __construct(
        private Factory $view,
        private PanelRenderer $panel,
        private Settings $settings,
        private RuntimeState $runtime,
        private TopicService $service,
        private SessionStore $session
    ) {
    }

    public function show(Request $request): Response
    {
        return $this->render($request, '', '');
    }

    public function save(Request $request): Response
    {
        try {
            $this->settings->save($request->all());
        } catch (InvalidArgumentException $error) {
            return $this->render($request, $error->getMessage(), '', 422);
        }

        return $this->panel->redirect('/module/mqtt/settings', 'Настройки сохранены, цикл переподключится.');
    }

    public function test(): Response
    {
        $error = $this->service->testConnection();
        if ($error !== null) {
            return $this->panel->redirect('/module/mqtt/settings', 'Брокер недоступен: ' . $error, 'danger');
        }

        return $this->panel->redirect('/module/mqtt/settings', 'Соединение с брокером установлено.');
    }

    private function render(Request $request, string $error, string $done, int $status = 200): Response
    {
        $content = $this->view->make('mqtt::settings', [
            'values' => $this->settings->all(),
            'schema' => Settings::schema(),
            'status' => $this->runtime->status(),
            'error' => $error,
            'done' => $done,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('MQTT · Настройки', $content, 'mqtt'), $status);
    }
}
