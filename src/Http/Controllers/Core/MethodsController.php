<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Methods\MethodRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Методы классов: CRUD без запуска кода.
 */
final readonly class MethodsController extends Controller
{
    public function __construct(
        private Factory $view,
        private MethodRepository $methods,
        private ClassRepository $classes,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(): Response
    {
        $classes = [];
        foreach ($this->classes->all() as $class) {
            $classes[$class['id']] = $class['title'];
        }

        $content = $this->view->make('pages.methods.index', [
            'methods' => $this->methods->all(),
            'classes' => $classes,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Методы', $content, 'methods'));
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        try {
            $this->methods->create(
                (int)$request->request->get('class_id', 0),
                (string)$request->request->get('title', ''),
                (string)$request->request->get('description', ''),
                (string)$request->request->get('code', '')
            );
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse('/module/methods');
    }

    public function edit(string $id): Response
    {
        $method = $this->methods->find((int)$id);

        return $method === null
            ? $this->panel->render(new PanelPage('Методы', '<p class="md-alert md-alert--danger">Метод не найден.</p>', 'methods'), 404)
            : $this->form($method);
    }

    public function update(string $id, Request $request): Response
    {
        $method = $this->methods->find((int)$id);
        if ($method === null) {
            return new RedirectResponse('/module/methods');
        }

        try {
            $this->methods->update(
                (int)$id,
                (int)$request->request->get('class_id', 0),
                (string)$request->request->get('title', ''),
                (string)$request->request->get('description', ''),
                (string)$request->request->get('code', '')
            );
        } catch (InvalidArgumentException $error) {
            return $this->form($method, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse('/module/methods');
    }

    public function delete(string $id): Response
    {
        $this->methods->delete((int)$id);

        return new RedirectResponse('/module/methods');
    }

    /**
     * @param array{id:int,class_id:int,title:string,description:string,code:string}|null $method
     * @param array<string, mixed>|null $draft
     */
    private function form(?array $method, ?string $error = null, ?array $draft = null, int $status = 200): Response
    {
        $content = $this->view->make('pages.methods.form', [
            'method' => $method,
            'draft' => $draft,
            'classes' => $this->classes->all(),
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage($method === null ? 'Новый метод' : 'Метод', $content, 'methods'), $status);
    }
}
