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
use MajorDoMo\Locations\LocationKind;
use MajorDoMo\Locations\LocationRepository;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Panel\RenameNotice;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertySubscriptionRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Свойства класса или объекта внутри раздела «Классы».
 *
 * Отдельного каталога свойств нет: форма открывается из карточки класса или
 * объекта, а после сохранения возвращает туда же. Значения у класса не
 * редактируются — только у конкретного объекта.
 *
 * 2026-10-29 — CRUD возвращает одноразовый Notify; формат переименования общий.
 * 2026-10-27 — страница стала контекстной формой класса/объекта.
 * 2026-10-27 — у свойства появилась роль в комнате для сбора активности.
 * 2026-10-27 — роль доступна только у свойства объекта, который расположен
 *              в комнате; для класса и дома/этажа выбор заблокирован.
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
        private RenameNotice $renames,
        private LocationRepository $locations,
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
        $object = $objectId > 0 ? $this->objects->find($objectId) : null;

        try {
            $this->properties->create(
                $classId,
                (string)$request->request->get('title', ''),
                (string)$request->request->get('description', ''),
                (string)$request->request->get('data_type', 'string'),
                (string)$request->request->get('keep_history', '') === '1',
                (string)$request->request->get('on_change', ''),
                $objectId,
                (string)$request->request->get('only_new_values', '') === '1',
                $this->roomRoleFor($request, $object)
            );
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), $request->request->all(), 422);
        }

        return $this->panel->redirect($this->returnUrl($classId, $objectId), 'Свойство создано.');
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
        $object = $objectId > 0 ? $this->objects->find($objectId) : null;

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
                (string)$request->request->get('only_new_values', '') === '1',
                $this->roomRoleFor($request, $object, (string)$property['room_role'])
            );
        } catch (InvalidArgumentException $error) {
            return $this->form($property, $error->getMessage(), $request->request->all(), 422);
        }

        return $this->panel->redirect($this->returnUrl($classId, $objectId), $this->renames->message(
            (string)$property['title'],
            (string)$request->request->get('title', ''),
            'Свойство сохранено.'
        ));
    }

    public function delete(string $id): Response
    {
        $property = $this->properties->find((int)$id);
        if ($property === null) {
            return new RedirectResponse('/classes');
        }

        $this->properties->delete((int)$id);

        return $this->panel->redirect(
            $this->returnUrl((int)$property['class_id'], (int)$property['object_id']),
            'Свойство удалено.'
        );
    }

    /**
     * @param array{id:int,class_id:int,object_id:int,title:string,description:string,data_type:string,keep_history:bool,only_new_values:bool,on_change:string,room_role:string}|null $property
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
        $roomRole = $this->roomRoleContext($object);

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
            'roomRoleAvailable' => $roomRole['available'],
            'roomRoleHint' => $roomRole['hint'],
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

    /**
     * Доступна ли роль в комнате для выбранного объекта.
     *
     * Роль описывает участие значения в активности помещения, поэтому она
     * имеет смысл только у объекта, который реально стоит в комнате. У класса
     * локации нет, а дом и этаж — не помещение.
     *
     * @param array{id:int,title:string,class_id:int,location_id:int,description:string}|null $object
     * @return array{available:bool,hint:string}
     */
    private function roomRoleContext(?array $object): array
    {
        if ($object === null) {
            return [
                'available' => false,
                'hint' => 'Роль в комнате доступна только у свойства объекта. У класса нет расположения: создайте объект, выберите ему комнату и задайте роль в свойстве объекта.',
            ];
        }
        $locationId = (int)$object['location_id'];
        if ($locationId <= 0) {
            return [
                'available' => false,
                'hint' => 'У объекта не выбрана локация. Укажите комнату в карточке объекта — после этого роль станет доступна.',
            ];
        }
        $location = $this->locations->find($locationId);
        if ($location === null) {
            return [
                'available' => false,
                'hint' => 'Локация объекта не найдена. Выберите комнату в карточке объекта.',
            ];
        }
        if ($location['kind'] !== LocationKind::ROOM) {
            return [
                'available' => false,
                'hint' => 'Объект расположен в локации «' . (string)$location['title'] . '» ('
                    . LocationKind::label((string)$location['kind'])
                    . '), а роль в комнате доступна только для объекта в комнате.',
            ];
        }

        return [
            'available' => true,
            'hint' => 'Система собирает значения выбранных свойств и обновляет активность, свет, обогрев и кондиционер комнаты «'
                . (string)$location['title'] . '».',
        ];
    }

    /**
     * Роль из запроса принимается только при доступной комнате.
     *
     * Заблокированное поле не приходит в POST; существующую роль в этом
     * случае сохраняем, чтобы правка названия или описания её не стирала.
     *
     * @param array{id:int,title:string,class_id:int,location_id:int,description:string}|null $object
     */
    private function roomRoleFor(Request $request, ?array $object, string $existing = ''): string
    {
        if (!$this->roomRoleContext($object)['available']) {
            return $existing;
        }

        return $request->request->has('room_role')
            ? (string)$request->request->get('room_role', '')
            : $existing;
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
