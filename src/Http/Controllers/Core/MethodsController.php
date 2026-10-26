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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Методы класса или объекта внутри раздела «Классы».
 *
 * Отдельного каталога методов нет: форма открывается из карточки класса или
 * объекта. Редактор не исполняет код: вызов идёт из сценария, API или
 * OnChange-обработчика.
 *
 * 2026-10-27 — страница стала контекстной формой класса/объекта.
 */
final readonly class MethodsController extends Controller
{
    public function __construct(
        private Factory $view,
        private MethodRepository $methods,
        private ClassRepository $classes,
        private ObjectRepository $objects,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function create(Request $request): Response
    {
        $classId = max(0, (int)$request->query->get('class_id', 0));
        $objectId = max(0, (int)$request->query->get('object_id', 0));
        $object = $objectId > 0 ? $this->objects->find($objectId) : null;
        if ($object !== null) {
            $classId = (int)$object['class_id'];
        }

        return $this->form(null, null, [
            'class_id' => $classId,
            'object_id' => (int)($object['id'] ?? $objectId),
        ]);
    }

    public function store(Request $request): Response
    {
        $classId = max(0, (int)$request->request->get('class_id', 0));
        $objectId = max(0, (int)$request->request->get('object_id', 0));

        try {
            $this->methods->create(
                $classId,
                (string)$request->request->get('title', ''),
                (string)$request->request->get('description', ''),
                (string)$request->request->get('code', ''),
                $objectId
            );
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse($this->returnUrl($classId, $objectId));
    }

    public function edit(string $id): Response
    {
        $method = $this->methods->find((int)$id);
        if ($method === null) {
            return $this->notFound();
        }

        return $this->form($method);
    }

    public function update(string $id, Request $request): Response
    {
        $method = $this->methods->find((int)$id);
        if ($method === null) {
            return new RedirectResponse('/classes');
        }

        $classId = max(0, (int)$request->request->get('class_id', $method['class_id']));
        $objectId = max(0, (int)$request->request->get('object_id', $method['object_id']));

        try {
            $this->methods->update(
                (int)$id,
                $classId,
                (string)$request->request->get('title', ''),
                (string)$request->request->get('description', ''),
                (string)$request->request->get('code', ''),
                $objectId
            );
        } catch (InvalidArgumentException $error) {
            return $this->form($method, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse($this->returnUrl($classId, $objectId));
    }

    public function delete(string $id): Response
    {
        $method = $this->methods->find((int)$id);
        if ($method === null) {
            return new RedirectResponse('/classes');
        }

        $this->methods->delete((int)$id);

        return new RedirectResponse($this->returnUrl((int)$method['class_id'], (int)$method['object_id']));
    }

    /**
     * @param array{id:int,class_id:int,object_id:int,title:string,description:string,code:string}|null $method
     * @param array<string, mixed>|null $draft
     */
    private function form(?array $method, ?string $error = null, ?array $draft = null, int $status = 200): Response
    {
        $classId = (int)($draft['class_id'] ?? $method['class_id'] ?? 0);
        $objectId = (int)($draft['object_id'] ?? $method['object_id'] ?? 0);
        $object = $objectId > 0 ? $this->objects->find($objectId) : null;
        if ($object !== null) {
            $classId = (int)$object['class_id'];
        }
        $class = $classId > 0 ? $this->classes->find($classId) : null;

        $content = $this->view->make('pages.methods.form', [
            'method' => $method,
            'draft' => $draft,
            'classes' => $this->classes->all(),
            'class' => $class,
            'object' => $object,
            'classId' => $classId,
            'objectId' => $objectId,
            'returnUrl' => $this->returnUrl($classId, $objectId),
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(
            new PanelPage($method === null ? 'Новый метод' : 'Метод: ' . (string)$method['title'], $content, 'classes'),
            $status
        );
    }

    private function notFound(): Response
    {
        $content = $this->view->make('pages.objects.missing', [])->render();

        return $this->panel->render(new PanelPage('Метод не найден', $content, 'classes'), 404);
    }

    private function returnUrl(int $classId, int $objectId): string
    {
        if ($objectId > 0) {
            return '/objects/' . $objectId . '/edit?tab=methods';
        }
        if ($classId > 0) {
            return '/classes/' . $classId . '/edit?tab=methods';
        }

        return '/classes';
    }
}
