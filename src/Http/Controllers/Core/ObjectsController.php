<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Events\PropertyEventRepository;
use MajorDoMo\History\HistoryRepository;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Locations\LocationRepository;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Properties\ValueRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Объекты: каталог, карточка объекта, значения свойств, история и журнал.
 *
 * Карточка повторяет рабочий сценарий alpha-модуля objects, но собрана на
 * новом ядре: вкладки — ссылки с `?tab=`, значения пишутся только через
 * `PropertyValueService`, поэтому история и OnChange срабатывают одинаково.
 *
 * 2026-10-26 — добавлены фильтры каталога и вкладки карточки, ссылки переведены
 * с legacy `/module/objects` на чистый `/objects`.
 */
final readonly class ObjectsController extends Controller
{
    private const TABS = ['details', 'properties', 'methods', 'history', 'journal'];

    public function __construct(
        private Factory $view,
        private ObjectRepository $objects,
        private ClassRepository $classes,
        private LocationRepository $locations,
        private PropertyRepository $properties,
        private MethodRepository $methods,
        private ValueRepository $values,
        private HistoryRepository $history,
        private PropertyEventRepository $events,
        private PropertyValueService $propertyValues,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $classId = max(0, (int)$request->query->get('class_id', 0));
        $locationId = max(0, (int)$request->query->get('location_id', 0));

        $classes = $this->classes->all();
        $locations = $this->locations->all();
        $objects = $this->objects->all($search, $classId, $locationId);

        $content = $this->view->make('pages.objects.index', [
            'objects' => $objects,
            'classes' => $classes,
            'locations' => $locations,
            'classNames' => $this->names($classes),
            'locationNames' => $this->names($locations),
            'search' => $search,
            'classId' => $classId,
            'locationId' => $locationId,
            'total' => $this->objects->countsByClass(),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Объекты', $content, 'objects'));
    }

    public function create(Request $request): Response
    {
        // Класс и локация могут прийти ссылкой из карточки класса/локации.
        $draft = [];
        $classId = max(0, (int)$request->query->get('class_id', 0));
        $locationId = max(0, (int)$request->query->get('location_id', 0));
        if ($classId > 0) {
            $draft['class_id'] = $classId;
        }
        if ($locationId > 0) {
            $draft['location_id'] = $locationId;
        }

        return $this->form(null, null, $draft === [] ? null : $draft);
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

        return new RedirectResponse('/objects/' . $id . '/edit');
    }

    public function edit(string $id, Request $request): Response
    {
        $object = $this->objects->find((int)$id);
        if ($object === null) {
            return $this->notFound();
        }

        $tab = (string)$request->query->get('tab', 'details');

        return $this->form($object, null, null, 200, in_array($tab, self::TABS, true) ? $tab : 'details');
    }

    public function update(string $id, Request $request): Response
    {
        $object = $this->objects->find((int)$id);
        if ($object === null) {
            return new RedirectResponse('/objects');
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
        foreach ($values as $propertyId => $value) {
            if (is_numeric($propertyId) && is_scalar($value)) {
                $this->propertyValues->setValue((int)$id, (int)$propertyId, (string)$value, 'panel:objects');
            }
        }

        return new RedirectResponse('/objects/' . $id . '/edit');
    }

    public function delete(string $id): Response
    {
        $this->objects->delete((int)$id);

        return new RedirectResponse('/objects');
    }

    /**
     * @param array{id:int,title:string,class_id:int,location_id:int,description:string}|null $object
     * @param array<string, mixed>|null $draft
     */
    private function form(?array $object, ?string $error = null, ?array $draft = null, int $status = 200, string $tab = 'details'): Response
    {
        $classId = (int)($draft['class_id'] ?? $object['class_id'] ?? 0);
        $properties = $classId > 0 ? $this->properties->forClass($classId) : [];
        $values = $object !== null ? $this->values->forObject($object['id']) : [];
        $objectId = (int)($object['id'] ?? 0);

        $content = $this->view->make('pages.objects.form', [
            'object' => $object,
            'draft' => $draft,
            'tab' => $object === null ? 'details' : $tab,
            'classes' => $this->classes->all(),
            'locations' => $this->locations->all(),
            'properties' => $properties,
            'values' => $values,
            'classMethods' => $classId > 0 ? $this->methods->forClass($classId) : [],
            'history' => $tab === 'history' && $objectId > 0 ? $this->history->forObject($objectId) : [],
            'journal' => $tab === 'journal' && $objectId > 0 ? $this->events->forObject($objectId) : [],
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        $title = $object === null ? 'Новый объект' : (string)$object['title'];

        return $this->panel->render(new PanelPage($object === null ? 'Новый объект' : 'Объект: ' . $title, $content, 'objects'), $status);
    }

    private function notFound(): Response
    {
        $content = $this->view->make('pages.objects.missing', [])->render();

        return $this->panel->render(new PanelPage('Объект не найден', $content, 'objects'), 404);
    }

    /**
     * @param list<array{id:int,title:string}> $rows
     * @return array<int,string>
     */
    private function names(array $rows): array
    {
        $names = [];
        foreach ($rows as $row) {
            $names[$row['id']] = $row['title'];
        }

        return $names;
    }
}
