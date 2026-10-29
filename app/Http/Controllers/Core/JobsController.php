<?php

declare(strict_types=1);

namespace LaIOT\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use LaIOT\Http\Controller;
use LaIOT\Http\PanelPage;
use LaIOT\Http\PanelRenderer;
use LaIOT\Http\Session\SessionStore;
use LaIOT\Services\Queue\Queue;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Очередь заданий LaIOT в alpha-layout.
 *
 * Модуль не владеет отдельной таблицей: он показывает core-очередь через
 * контракт Queue, поэтому работает и с database, и с будущими драйверами.
 */
final readonly class JobsController extends Controller
{
    public function __construct(
        private Factory $view,
        private Queue $queue,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(): Response
    {
        $content = $this->view->make('pages.jobs.index', [
            'stats' => $this->queue->stats('default'),
            'pending' => $this->queue->pending('default'),
            'failed' => $this->queue->failed('default'),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Очередь', $content, 'jobs'));
    }

    public function retry(string $id): Response
    {
        $this->queue->retry((int)$id);

        return new RedirectResponse('/jobs');
    }

    public function forget(string $id): Response
    {
        $this->queue->forget((int)$id);

        return new RedirectResponse('/jobs');
    }
}
