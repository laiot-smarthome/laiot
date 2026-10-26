<?php

declare(strict_types=1);

namespace MajorDoMo\Modules\Core\Objects;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Locations\LocationRepository;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\ValueRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Объекты: класс, локация и значения свойств.
 *
 * Методы/история объектов — следующий слой.
 */
final readonly class ObjectsController extends Controller
{
    public function __construct(
        private Factory $view,
        private ObjectRepository $objects,
        private ClassRepository $classes,
        private LocationRepository $locations,
        private PropertyRepository $properties,
        private ValueRepository $values,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $classNames = [];
        foreach ($this->classes->all() as $class) {
            $classNames[$class['id']] = $class['title'];
        }
        $locationNames = [];
        foreach ($this->locations->all() as $location) {
            $locationNames[$location['id']] = $location['title'];
        }

        $content = $this->view->make('objects::index', [
            'objects' => $this->objects->all($search),
            'classes' => $classNames,
            'locations' => $locationNames,
            'search' => $search,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Объекты', $content, 'objects'));
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        try {
            $id = $this->objects->create(
                (string)$request->request->get('title', ''),
                (int)$request->request->get('class_id', 0),
                (int)$request->request->get('location_id', 0),
                (string)$request->request->get('description', '')
            );
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse('/module/objects/' . $id . '/edit');
    }

    public function edit(string $id): Response
    {
        $object = $this->objects->find((int)$id);

        return $object === null
            ? $this->panel->render(new PanelPage('Объекты', '<p class="md-alert md-alert--danger">Объект не найден.</p>', 'objects'), 404)
            : $this->form($object);
    }

    public function update(string $id, Request $request): Response
    {
        $object = $this->objects->find((int)$id);
        if ($object === null) {
            return new RedirectResponse('/module/objects');
        }

        try {
            $this->objects->update(
                (int)$id,
                (string)$request->request->get('title', ''),
                (int)$request->request->get('class_id', 0),
                (int)$request->request->get('location_id', 0),
                (string)$request->request->get('description', '')
            );
        } catch (InvalidArgumentException $error) {
            return $this->form($object, $error->getMessage(), $request->request->all(), 422);
        }

        $values = $request->request->all('values');
        if (is_array($values)) {
            foreach ($values as $propertyId => $value) {
                if (is_numeric($propertyId) && is_scalar($value)) {
                    $this->values->set((int)$id, (int)$propertyId, (string)$value);
                }
            }
        }

        return new RedirectResponse('/module/objects/' . $id . '/edit');
    }

    public function delete(string $id): Response
    {
        $this->objects->delete((int)$id);

        return new RedirectResponse('/module/objects');
    }

    /**
     * @param array{id:int,title:string,class_id:int,location_id:int,description:string}|null $object
     * @param array<string, mixed>|null $draft
     */
    private function form(?array $object, ?string $error = null, ?array $draft = null, int $status = 200): Response
    {
        $classId = (int)($draft['class_id'] ?? $object['class_id'] ?? 0);
        $properties = $classId > 0 ? $this->properties->forClass($classId) : [];
        $values = $object !== null ? $this->values->forObject($object['id']) : [];

        $content = $this->view->make('objects::form', [
            'object' => $object,
            'draft' => $draft,
            'classes' => $this->classes->all(),
            'locations' => $this->locations->all(),
            'properties' => $properties,
            'values' => $values,
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage($object === null ? 'Новый объект' : 'Объект', $content, 'objects'), $status);
    }
}
