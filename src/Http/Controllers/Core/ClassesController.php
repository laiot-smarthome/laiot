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
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Классы объектов: дерево классов, свойства и методы класса.
 *
 * 2026-10-26 — страница переведена на дерево `md-tree-table` с раскрытием
 * объектов класса, добавлены счётчики и вкладки карточки класса.
 */
final readonly class ClassesController extends Controller
{
    private const TABS = ['properties', 'methods', 'objects'];

    public function __construct(
        private Factory $view,
        private ClassRepository $classes,
        private PropertyRepository $properties,
        private MethodRepository $methods,
        private ObjectRepository $objects,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $classes = $this->classes->all();
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $classes = array_values(array_filter(
                $classes,
                static fn (array $class): bool => str_contains(mb_strtolower($class['title']), $needle)
            ));
        }

        $content = $this->view->make('pages.classes.index', [
            'classes' => $classes,
            'objectsByClass' => $this->safeObjects(),
            'propertyCounts' => $this->safeCount(fn (): array => $this->properties->countsByClass()),
            'methodCounts' => $this->safeCount(fn (): array => $this->methods->countsByClass()),
            'search' => $search,
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
            $id = $this->classes->create((string)$request->request->get('title', ''));
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse('/classes/' . $id . '/edit');
    }

    public function edit(string $id, Request $request): Response
    {
        $class = $this->classes->find((int)$id);
        if ($class === null) {
            return $this->notFound();
        }

        $tab = (string)$request->query->get('tab', '');

        return $this->form($class, null, null, 200, in_array($tab, self::TABS, true) ? $tab : '');
    }

    public function update(string $id, Request $request): Response
    {
        $class = $this->classes->find((int)$id);
        if ($class === null) {
            return new RedirectResponse('/classes');
        }

        try {
            $this->classes->update((int)$id, (string)$request->request->get('title', ''));
        } catch (InvalidArgumentException $error) {
            return $this->form($class, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse('/classes/' . $id . '/edit');
    }

    public function delete(string $id): Response
    {
        $this->classes->delete((int)$id);

        return new RedirectResponse('/classes');
    }

    /**
     * @param array{id:int,title:string}|null $class
     * @param array<string, mixed>|null $draft
     */
    private function form(?array $class, ?string $error = null, ?array $draft = null, int $status = 200, string $tab = ''): Response
    {
        $classId = (int)($class['id'] ?? 0);
        $content = $this->view->make('pages.classes.form', [
            'class' => $class,
            'draft' => $draft,
            'tab' => $tab,
            'properties' => $classId > 0 ? $this->properties->forClass($classId) : [],
            'methods' => $classId > 0 ? $this->methods->forClass($classId) : [],
            'objects' => $classId > 0 ? ($this->safeObjects()[$classId] ?? []) : [],
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(
            new PanelPage($class === null ? 'Новый класс' : 'Класс: ' . (string)$class['title'], $content, 'classes'),
            $status
        );
    }

    private function notFound(): Response
    {
        $content = $this->view->make('pages.objects.missing', [])->render();

        return $this->panel->render(new PanelPage('Класс не найден', $content, 'classes'), 404);
    }

    /**
     * @return array<int,list<array{id:int,title:string}>>
     */
    private function safeObjects(): array
    {
        try {
            return $this->objects->groupedByClass();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Счётчики читаются с защитой: на частичной схеме таблицы может не быть.
     *
     * @param callable(): array<int,int> $callback
     * @return array<int,int>
     */
    private function safeCount(callable $callback): array
    {
        try {
            return $callback();
        } catch (Throwable) {
            return [];
        }
    }
}
