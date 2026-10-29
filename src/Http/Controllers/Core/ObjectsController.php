<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
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
use MajorDoMo\Panel\RenameNotice;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertySubscriptionRepository;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Properties\ValueRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Карточка объекта внутри раздела «Классы».
 *
 * 2026-10-29 — значения проверяются до записи и сохраняются одним пакетом;
 * ошибки остаются во вкладке свойств вместе с введёнными значениями.
 * 2026-10-27 — запрет удаления связанного пользователя показывается в форме.
 * Объект — единственное место, где существуют значения свойств и их история.
 * Класс задаёт свойства/методы, объект получает значения; объектные свойства
 * и методы действуют только у этого объекта.
 *
 * 2026-10-27 — убран отдельный каталог объектов, значения редактируются во
 * вкладке «Свойства», добавлены объектные свойства/методы и наследование.
 * 2026-10-27 — класс обязателен, имя объекта проверяется латиницей и
 * уникальностью, расположение задаётся только у объекта.
 */
final readonly class ObjectsController extends Controller
{
    private const TABS = ['details', 'properties', 'methods', 'history', 'journal'];

    public function __construct(
        private Factory $view,
        private ValidationFactory $validation,
        private ObjectRepository $objects,
        private ClassRepository $classes,
        private LocationRepository $locations,
        private PropertyRepository $properties,
        private PropertySubscriptionRepository $subscriptions,
        private MethodRepository $methods,
        private ValueRepository $values,
        private HistoryRepository $history,
        private PropertyEventRepository $events,
        private PropertyValueService $propertyValues,
        private RenameNotice $renames,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    /**
     * Отдельного каталога объектов больше нет: вход в модель дома — класс.
     */
    public function index(): Response
    {
        return new RedirectResponse('/classes');
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

        return $this->panel->redirect('/objects/' . $id . '/edit?tab=properties', 'Объект создан.');
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
            return new RedirectResponse('/classes');
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

        return $this->panel->redirect('/objects/' . $id . '/edit', $this->renames->message(
            (string)$object['title'],
            (string)$request->request->get('title', ''),
            'Объект сохранён.'
        ));
    }

    /**
     * Значения пишутся только через PropertyValueService: история, журнал и
     * OnChange срабатывают одинаково с панелью, сценарием и API.
     */
    public function updateValues(string $id, Request $request): Response
    {
        $object = $this->objects->find((int)$id);
        if ($object === null) {
            return new RedirectResponse('/classes');
        }

        $properties = $this->properties->forObject((int)$id);
        $ids = array_column($properties, 'id');
        $validator = $this->validation->make($request->request->all(), [
            'values' => ['required', $ids === [] ? 'array' : 'array:' . implode(',', $ids), $ids === [] ? 'max:0' : 'min:1'],
            'values.*' => ['nullable', 'string'],
        ], [], ['values' => 'Значения свойств', 'values.*' => 'Значение свойства']);
        if ($validator->fails()) {
            $raw = $request->request->all()['values'] ?? [];
            $draft = ['values' => is_array($raw) ? array_map(static fn (mixed $value): string => is_scalar($value) ? (string)$value : '', $raw) : []];

            return $this->form($object, 'Значения не сохранены. Проверьте поля формы.', $draft, 422, 'properties', $validator->errors()->toArray());
        }

        try {
            $this->propertyValues->setValues((int)$id, $validator->validated()['values'], 'panel:objects');
        } catch (InvalidArgumentException $error) {
            return $this->form($object, $error->getMessage(), $validator->validated(), 422, 'properties');
        }

        return $this->panel->redirect('/objects/' . $id . '/edit?tab=properties', 'Значения сохранены.');
    }

    public function delete(string $id): Response
    {
        $object = $this->objects->find((int)$id);
        if ($object === null) {
            return new RedirectResponse('/classes');
        }

        try {
            $this->objects->delete((int)$id);
        } catch (InvalidArgumentException $error) {
            return $this->form($object, $error->getMessage(), null, 422);
        }

        return $this->panel->redirect(
            (int)$object['class_id'] > 0
                ? '/classes/' . (int)$object['class_id'] . '/edit?tab=objects'
                : '/classes',
            'Объект удалён.'
        );
    }

    /**
     * @param array{id:int,title:string,class_id:int,location_id:int,description:string}|null $object
     * @param array<string, mixed>|null $draft
     * @param array<string,list<string>> $valueErrors
     */
    private function form(?array $object, ?string $error = null, ?array $draft = null, int $status = 200, string $tab = 'details', array $valueErrors = []): Response
    {
        $classId = (int)($draft['class_id'] ?? $object['class_id'] ?? 0);
        $class = $classId > 0 ? $this->classes->find($classId) : null;
        $objectId = (int)($object['id'] ?? 0);
        $properties = $object !== null ? $this->properties->forObject($objectId) : [];
        $classMethods = $object !== null ? $this->methods->forObject($objectId) : [];
        $values = $object !== null ? $this->values->forObject($objectId) : [];
        $draftValues = is_array($draft['values'] ?? null) ? $draft['values'] : [];
        $lineage = $class !== null ? $this->classes->lineage($classId) : [];
        $path = implode(' → ', array_map(
            static fn (array $row): string => (string)$row['title'],
            array_reverse($lineage)
        ));

        $content = $this->view->make('pages.objects.form', [
            'object' => $object,
            'draft' => $draft,
            'tab' => $object === null ? 'details' : (in_array($tab, self::TABS, true) ? $tab : 'details'),
            'classes' => $this->classes->all(),
            'class' => $class,
            'classPath' => $path,
            'locations' => $this->locations->all(),
            'takenNames' => $this->objects->titles($objectId),
            'properties' => $properties,
            'values' => $draftValues + $values,
            'valueErrors' => $valueErrors,
            'propertyBindings' => $this->subscriptions->forProperties(array_map('intval', array_column($properties, 'id'))),
            'classMethods' => $classMethods,
            'objectPropertyCount' => count(array_filter($properties, static fn (array $row): bool => $row['origin'] === 'object')),
            'history' => $tab === 'history' && $objectId > 0 ? $this->history->forObject($objectId) : [],
            'journal' => $tab === 'journal' && $objectId > 0 ? $this->events->forObject($objectId) : [],
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        $title = $object === null ? 'Новый объект' : (string)$object['title'];

        return $this->panel->render(new PanelPage($object === null ? 'Новый объект' : 'Объект: ' . $title, $content, 'classes'), $status);
    }

    private function notFound(): Response
    {
        $content = $this->view->make('pages.objects.missing', [])->render();

        return $this->panel->render(new PanelPage('Объект не найден', $content, 'classes'), 404);
    }

}
