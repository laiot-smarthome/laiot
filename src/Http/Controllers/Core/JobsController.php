<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Queue\Queue;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Очередь заданий MyDoMo в alpha-layout.
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

        return new RedirectResponse('/module/jobs');
    }

    public function forget(string $id): Response
    {
        $this->queue->forget((int)$id);

        return new RedirectResponse('/module/jobs');
    }
}
