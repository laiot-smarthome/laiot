<?php

declare(strict_types=1);

namespace MajorDoMo\Zigbee\Http;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Zigbee\Repository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Диагностический журнал Zigbee.
 *
 * Журнал ограничен последними 1000 записями на экземпляр и служит для
 * разбора сообщений, а не архивом. Фильтры и страницы живут в Yoyo.
 *
 * 2026-10-28 — журнал перенесён из alpha `zigbee_events`.
 */
final readonly class JournalController extends Controller
{
    public function __construct(
        private Factory $view,
        private PanelRenderer $panel,
        private Repository $repository,
        private SessionStore $session
    ) {
    }

    public function index(): Response
    {
        $content = $this->view->make('zigbee::journal', [
            'bridges' => $this->repository->bridges(),
            'counts' => $this->repository->counts(),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Zigbee · журнал', $content, 'zigbee'));
    }

    public function clear(Request $request): Response
    {
        $bridgeId = max(0, (int)$request->input('bridge_id', 0));
        $removed = $this->repository->clearEvents($bridgeId > 0 ? $bridgeId : null);

        return $this->panel->redirect('/module/zigbee/journal', 'Журнал очищен: ' . $removed . ' записей.');
    }
}
