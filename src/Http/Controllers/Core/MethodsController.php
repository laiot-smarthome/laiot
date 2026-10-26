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
 * Методы классов: каталог и редактор кода.
 *
 * Метод выполняется только пользовательским кодом через `callMethod`, из этой
 * страницы запуска нет: редактор не должен исполнять код при сохранении.
 *
 * 2026-10-26 — добавлены поиск и фильтр по классу, форма собрана на md-ui-form.
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

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $classId = max(0, (int)$request->query->get('class_id', 0));
        $classes = $this->classes->all();

        $names = [0 => 'Без класса'];
        foreach ($classes as $class) {
            $names[$class['id']] = $class['title'];
        }

        $content = $this->view->make('pages.methods.index', [
            'methods' => $this->methods->all($search, $classId),
            'classes' => $classes,
            'classNames' => $names,
            'search' => $search,
            'classId' => $classId,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Методы', $content, 'methods'));
    }

    public function create(Request $request): Response
    {
        // Класс можно передать ссылкой из карточки класса: тогда селект уже
        // заполнен и пользователь не выбирает класс повторно.
        $classId = max(0, (int)$request->query->get('class_id', 0));

        return $this->form(null, null, $classId > 0 ? ['class_id' => $classId] : null);
    }

    public function store(Request $request): Response
    {
        try {
            $id = $this->methods->create(
                (int)$request->request->get('class_id', 0),
                (string)$request->request->get('title', ''),
                (string)$request->request->get('description', ''),
                (string)$request->request->get('code', '')
            );
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse('/methods/' . $id . '/edit');
    }

    public function edit(string $id): Response
    {
        $method = $this->methods->find((int)$id);

        return $method === null
            ? $this->panel->render(new PanelPage('Метод не найден', '<p class="md-alert md-alert--danger">Метод не найден.</p>', 'methods'), 404)
            : $this->form($method);
    }

    public function update(string $id, Request $request): Response
    {
        $method = $this->methods->find((int)$id);
        if ($method === null) {
            return new RedirectResponse('/methods');
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

        return new RedirectResponse('/methods');
    }

    public function delete(string $id): Response
    {
        $this->methods->delete((int)$id);

        return new RedirectResponse('/methods');
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

        return $this->panel->render(
            new PanelPage($method === null ? 'Новый метод' : 'Метод: ' . (string)$method['title'], $content, 'methods'),
            $status
        );
    }
}
