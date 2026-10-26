<?php

declare(strict_types=1);

namespace MajorDoMo\Modules\Core\Classes;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Классы объектов: CRUD. Методы/свойства — следующий этап.
 */
final readonly class ClassesController extends Controller
{
    public function __construct(
        private Factory $view,
        private ClassRepository $classes,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(): Response
    {
        $content = $this->view->make('classes::index', [
            'classes' => $this->classes->all(),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Классы', $content, 'classes'));
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        try {
            $this->classes->create((string)$request->request->get('title', ''));
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), (string)$request->request->get('title', ''), 422);
        }

        return new RedirectResponse('/module/classes');
    }

    public function edit(string $id): Response
    {
        $class = $this->classes->find((int)$id);

        return $class === null
            ? $this->panel->render(new PanelPage('Классы', '<p class="md-alert md-alert--danger">Класс не найден.</p>', 'classes'), 404)
            : $this->form($class);
    }

    public function update(string $id, Request $request): Response
    {
        if ($this->classes->find((int)$id) === null) {
            return new RedirectResponse('/module/classes');
        }

        try {
            $this->classes->update((int)$id, (string)$request->request->get('title', ''));
        } catch (InvalidArgumentException $error) {
            return $this->form(['id' => (int)$id, 'title' => (string)$request->request->get('title', '')], $error->getMessage(), (string)$request->request->get('title', ''), 422);
        }

        return new RedirectResponse('/module/classes');
    }

    public function delete(string $id): Response
    {
        $this->classes->delete((int)$id);

        return new RedirectResponse('/module/classes');
    }

    /**
     * @param array{id:int,title:string}|null $class
     */
    private function form(?array $class, ?string $error = null, ?string $title = null, int $status = 200): Response
    {
        $content = $this->view->make('classes::form', [
            'class' => $class,
            'title' => $title ?? (string)($class['title'] ?? ''),
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage($class === null ? 'Новый класс' : 'Класс', $content, 'classes'), $status);
    }
}
