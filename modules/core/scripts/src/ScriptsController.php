<?php

declare(strict_types=1);

namespace MajorDoMo\Modules\Core\Scripts;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Scripts\ScriptRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Пользовательские скрипты: редактирование кода, без запуска циклов.
 */
final readonly class ScriptsController extends Controller
{
    public function __construct(
        private Factory $view,
        private ScriptRepository $scripts,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $content = $this->view->make('scripts::index', [
            'scripts' => $this->scripts->all($search),
            'search' => $search,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Скрипты', $content, 'scripts'));
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        try {
            $id = $this->scripts->create(
                (string)$request->request->get('title', ''),
                (string)$request->request->get('code', ''),
                (string)$request->request->get('description', ''),
                (int)$request->request->get('category_id', 0),
                (string)$request->request->get('run_periodically', '') === '1'
            );
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse('/module/scripts/' . $id . '/edit');
    }

    public function edit(string $id): Response
    {
        $script = $this->scripts->find((int)$id);

        return $script === null
            ? $this->panel->render(new PanelPage('Скрипты', '<p class="md-alert md-alert--danger">Скрипт не найден.</p>', 'scripts'), 404)
            : $this->form($script);
    }

    public function update(string $id, Request $request): Response
    {
        $script = $this->scripts->find((int)$id);
        if ($script === null) {
            return new RedirectResponse('/module/scripts');
        }

        try {
            $this->scripts->update(
                (int)$id,
                (string)$request->request->get('title', ''),
                (string)$request->request->get('code', ''),
                (string)$request->request->get('description', ''),
                (int)$request->request->get('category_id', 0),
                (string)$request->request->get('run_periodically', '') === '1'
            );
        } catch (InvalidArgumentException $error) {
            return $this->form($script, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse('/module/scripts');
    }

    public function delete(string $id): Response
    {
        $this->scripts->delete((int)$id);

        return new RedirectResponse('/module/scripts');
    }

    /**
     * @param array{id:int,title:string,code:string,description:string,category_id:int,run_periodically:bool}|null $script
     * @param array<string, mixed>|null $draft
     */
    private function form(?array $script, ?string $error = null, ?array $draft = null, int $status = 200): Response
    {
        $content = $this->view->make('scripts::form', [
            'script' => $script,
            'draft' => $draft,
            'categories' => $this->scripts->categories(),
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage($script === null ? 'Новый скрипт' : 'Скрипт', $content, 'scripts'), $status);
    }
}
