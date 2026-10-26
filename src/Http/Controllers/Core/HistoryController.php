<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\History\HistoryRepository;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Objects\ObjectRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * История значений свойств.
 */
final readonly class HistoryController extends Controller
{
    public function __construct(
        private Factory $view,
        private HistoryRepository $history,
        private ObjectRepository $objects,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $objectId = max(0, (int)$request->query->get('object_id', 0));
        $content = $this->view->make('pages.history.index', [
            'rows' => $this->history->all($search, 200, $objectId),
            'objects' => $this->objects->all(),
            'search' => $search,
            'objectId' => $objectId,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('История', $content, 'history'));
    }

    public function delete(string $id): Response
    {
        $this->history->delete((int)$id);

        return new RedirectResponse('/history');
    }
}
