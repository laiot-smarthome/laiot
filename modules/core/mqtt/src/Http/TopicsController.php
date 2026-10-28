<?php

declare(strict_types=1);

namespace MajorDoMo\Mqtt\Http;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Mqtt\BindingIndex;
use MajorDoMo\Mqtt\RuntimeState;
use MajorDoMo\Mqtt\Settings;
use MajorDoMo\Mqtt\TopicRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Главная страница MQTT: состояние цикла и список топиков.
 *
 * Список живёт в Yoyo-компоненте `mqtt-topics`; здесь только шапка,
 * избранные действия и включение автообновления значений.
 *
 * 2026-10-27 — страница MQTT вместо alpha `panel/mqtt.html`.
 */
final readonly class TopicsController extends Controller
{
    public function __construct(
        private Factory $view,
        private PanelRenderer $panel,
        private Settings $settings,
        private RuntimeState $runtime,
        private TopicRepository $topics,
        private BindingIndex $bindings,
        private SessionStore $session
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $content = $this->view->make('mqtt::index', [
            'settings' => $this->settings->all(),
            'status' => $this->runtime->status(),
            'done' => trim((string)$request->query->get('done', '')),
            'error' => trim((string)$request->query->get('error', '')),
            'csrf' => $this->session->csrfToken(),
            'queries' => $this->settings->queries(),
        ])->render();

        return $this->panel->render(new PanelPage('MQTT', $content, 'mqtt'));
    }

    public function clearTrash(): Response
    {
        $removed = $this->topics->clearTrash();
        $this->bindings->invalidate();

        return new RedirectResponse('/module/mqtt?done=' . rawurlencode('Удалено топиков без привязки: ' . $removed));
    }

    public function pruneHistory(): Response
    {
        $removed = $this->topics->pruneHistory($this->settings->int('history_keep_days'));

        return new RedirectResponse('/module/mqtt?done=' . rawurlencode('История старше срока удалена: ' . $removed));
    }
}
