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
use MajorDoMo\Properties\PropertySubscriptionRepository;
use MajorDoMo\Rename\NameReferenceUpdater;
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
        private MethodRepository $methods,
        private PropertySubscriptionRepository $subscriptions,
        private NameReferenceUpdater $references,
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
                $objectId,
                (string)$request->request->get('only_new_values', '') === '1'
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
                $objectId,
                (string)$request->request->get('only_new_values', '') === '1'
            );
        } catch (InvalidArgumentException $error) {
            return $this->form($property, $error->getMessage(), $request->request->all(), 422);
        }

        $this->rememberRename((string)$property['title'], (string)$request->request->get('title', ''));

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
     * @param array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,only_new_values:bool,on_change:string}|null $property
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
        $propertyId = (int)($property['id'] ?? 0);
        $methodOptions = $object !== null
            ? $this->methods->forObject((int)$object['id'])
            : ($classId > 0 ? $this->methods->effectiveForClass($classId) : []);

        $content = $this->view->make('pages.properties.form', [
            'property' => $property,
            'draft' => $draft,
            'classes' => $this->classes->all(),
            'class' => $class,
            'object' => $object,
            'classId' => $classId,
            'objectId' => $objectId,
            'keepHistory' => $draft !== null
                ? (string)($draft['keep_history'] ?? '') === '1'
                : (bool)($property['keep_history'] ?? false),
            'onlyNewValues' => $draft !== null
                ? (string)($draft['only_new_values'] ?? '') === '1'
                : (bool)($property['only_new_values'] ?? false),
            'bindings' => $propertyId > 0 ? $this->subscriptions->forProperty($propertyId) : [],
            'methodOptions' => $methodOptions,
            'takenNames' => $this->properties->takenNames($classId, $objectId, $propertyId),
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

    private function rememberRename(string $old, string $new): void
    {
        $old = trim($old);
        $new = trim($new);
        if ($old === '' || $old === $new) {
            return;
        }
        $message = 'Имя «' . $old . '» изменено на «' . $new . '». Обновлено ссылок: '
            . $this->references->changes() . '.';
        $warnings = $this->references->warnings();
        if ($warnings !== []) {
            $message .= ' Не удалось обновить файлов модулей: ' . count($warnings) . ' (подробности в логе).';
        }
        $this->session->set('panel.notice', $message);
    }
}
