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
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Свойства класса или объекта внутри раздела «Классы».
 *
 * Отдельного каталога свойств нет: форма открывается из карточки класса или
 * объекта, а после сохранения возвращает туда же. Значения у класса не
 * редактируются — только у конкретного объекта.
 *
 * 2026-10-27 — страница стала контекстной формой класса/объекта.
 */
final readonly class PropertiesController extends Controller
{
    public function __construct(
        private Factory $view,
        private PropertyRepository $properties,
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
            $this->properties->create(
                $classId,
                (string)$request->request->get('title', ''),
                (string)$request->request->get('description', ''),
                (string)$request->request->get('data_type', 'string'),
                (string)$request->request->get('keep_history', '') === '1',
                (string)$request->request->get('on_change', ''),
                $objectId
            );
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse($this->returnUrl($classId, $objectId));
    }

    public function edit(string $id): Response
    {
        $property = $this->properties->find((int)$id);
        if ($property === null) {
            return $this->notFound();
        }

        return $this->form($property);
    }

    public function update(string $id, Request $request): Response
    {
        $property = $this->properties->find((int)$id);
        if ($property === null) {
            return new RedirectResponse('/classes');
        }

        $classId = max(0, (int)$request->request->get('class_id', $property['class_id']));
        $objectId = max(0, (int)$request->request->get('object_id', $property['object_id']));

        try {
            $this->properties->update(
                (int)$id,
                $classId,
                (string)$request->request->get('title', ''),
                (string)$request->request->get('description', ''),
                (string)$request->request->get('data_type', 'string'),
                (string)$request->request->get('keep_history', '') === '1',
                (string)$request->request->get('on_change', ''),
                $objectId
            );
        } catch (InvalidArgumentException $error) {
            return $this->form($property, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse($this->returnUrl($classId, $objectId));
    }

    public function delete(string $id): Response
    {
        $property = $this->properties->find((int)$id);
        if ($property === null) {
            return new RedirectResponse('/classes');
        }

        $this->properties->delete((int)$id);

        return new RedirectResponse($this->returnUrl((int)$property['class_id'], (int)$property['object_id']));
    }

    /**
     * @param array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string}|null $property
     * @param array<string, mixed>|null $draft
     */
    private function form(?array $property, ?string $error = null, ?array $draft = null, int $status = 200): Response
    {
        $classId = (int)($draft['class_id'] ?? $property['class_id'] ?? 0);
        $objectId = (int)($draft['object_id'] ?? $property['object_id'] ?? 0);
        $object = $objectId > 0 ? $this->objects->find($objectId) : null;
        if ($object !== null) {
            $classId = (int)$object['class_id'];
        }
        $class = $classId > 0 ? $this->classes->find($classId) : null;

        $content = $this->view->make('pages.properties.form', [
            'property' => $property,
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
            new PanelPage($property === null ? 'Новое свойство' : 'Свойство: ' . (string)$property['title'], $content, 'classes'),
            $status
        );
    }

    private function notFound(): Response
    {
        $content = $this->view->make('pages.objects.missing', [])->render();

        return $this->panel->render(new PanelPage('Свойство не найдено', $content, 'classes'), 404);
    }

    private function returnUrl(int $classId, int $objectId): string
    {
        if ($objectId > 0) {
            return '/objects/' . $objectId . '/edit?tab=properties';
        }
        if ($classId > 0) {
            return '/classes/' . $classId . '/edit?tab=properties';
        }

        return '/classes';
    }
}
