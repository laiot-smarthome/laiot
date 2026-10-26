<?php

declare(strict_types=1);

namespace MajorDoMo\Modules\Core\ActionsLog;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Audit\ActionLogRepository;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Журнал действий в alpha-layout.
 *
 * Таблица `actions_log` остаётся legacy alpha; модуль только читает её через
 * ActionLogRepository и не дублирует uppercase-схему.
 */
final readonly class ActionsLogController extends Controller
{
    public function __construct(
        private Factory $view,
        private ActionLogRepository $log,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $type = trim((string)$request->query->get('type', ''));
        $search = trim((string)$request->query->get('q', ''));

        $content = $this->view->make('actions_log::index', [
            'entries' => $this->log->latest(200, $type, $search),
            'types' => $this->log->types(),
            'type' => $type,
            'search' => $search,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Журнал действий', $content, 'actions_log'));
    }

    public function delete(string $id): Response
    {
        $this->log->delete((int)$id);

        return new RedirectResponse('/module/actions_log');
    }
}
