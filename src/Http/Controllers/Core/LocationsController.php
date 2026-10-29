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
use MajorDoMo\Locations\RoomActivityService;
use MajorDoMo\Locations\RoomProvisioner;
use MajorDoMo\Objects\ObjectRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Локации: дом, этаж, комната и их активность.
 *
 * 2026-10-29 — сбои состояния не маскируются пустым списком; ошибка таймера
 * попадает в журнал и Notify, результат CRUD подтверждается уведомлением.
 * 2026-10-27 — добавлены иерархия, тип локации, таймаут активности и
 * автоматически созданная модель комнаты. Карточка комнаты показывает
 * объект, класс, состояние и свойства с ролью.
 * 2026-10-26 — добавлены поиск и количество объектов в помещении.
 * 2026-10-27 — локация принадлежит объекту: карточка показывает объекты
 * помещения и не привязывает к нему класс.
 */
final readonly class LocationsController extends Controller
{
    private const FLASH_ERROR = 'locations.error';

    public function __construct(
        private Factory $view,
        private LocationRepository $locations,
        private ObjectRepository $objects,
        private ClassRepository $classes,
        private RoomActivityService $activity,
        private LoggerInterface $logger,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));

        $content = $this->view->make('pages.locations.index', [
            'locations' => $this->locations->flattened($search),
            'objectCounts' => $this->locations->objectCounts(),
            'kinds' => LocationKind::labels(),
            'search' => $search,
            'error' => $this->pullError(),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Локации', $content, 'locations'));
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        try {
            $id = $this->locations->create(
                (string)$request->request->get('title', ''),
                (int)$request->request->get('priority', 0),
                (int)$request->request->get('parent_id', 0),
                (string)$request->request->get('kind', LocationKind::ROOM),
                (int)$request->request->get('activity_timeout', RoomProvisioner::DEFAULT_ACTIVITY_TIMEOUT)
            );
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), $request->request->all(), 422);
        }

        return $this->panel->redirect('/locations/' . $id . '/edit', 'Локация создана.');
    }

    public function edit(string $id): Response
    {
        $location = $this->locations->find((int)$id);

        return $location === null
            ? $this->panel->render(new PanelPage('Локация не найдена', '<p class="md-alert md-alert--danger">Локация не найдена.</p>', 'locations'), 404)
            : $this->form($location);
    }

    public function update(string $id, Request $request): Response
    {
        $location = $this->locations->find((int)$id);
        if ($location === null) {
            return new RedirectResponse('/locations');
        }

        try {
            $this->locations->update(
                (int)$id,
                (string)$request->request->get('title', ''),
                (int)$request->request->get('priority', 0),
                (int)$request->request->get('activity_timeout', (int)$location['activity_timeout'])
            );
        } catch (InvalidArgumentException $error) {
            return $this->form($location, $error->getMessage(), $request->request->all(), 422);
        }

        $updated = $this->locations->find((int)$id);
        if ($updated !== null && $updated['kind'] === LocationKind::ROOM) {
            try {
                $this->activity->reschedule($updated);
            } catch (Throwable $error) {
                $this->logger->error('Не удалось пересчитать таймер комнаты.', ['exception' => $error, 'location_id' => (int)$id]);

                return $this->panel->redirect('/locations', 'Локация сохранена, но таймер активности не обновлён. Подробности в журнале.', 'warning');
            }
        }

        return $this->panel->redirect('/locations', 'Локация сохранена.');
    }

    public function delete(string $id): Response
    {
        try {
            $this->locations->delete((int)$id);
        } catch (InvalidArgumentException $error) {
            $this->session->set(self::FLASH_ERROR, $error->getMessage());

            return new RedirectResponse('/locations');
        }

        return $this->panel->redirect('/locations', 'Локация удалена.');
    }

    /**
     * @param array{id:int,title:string,priority:int,parent_id:int,kind:string,class_id:int,object_id:int,activity_timeout:int}|null $location
     * @param array<string, mixed>|null $draft
     */
    private function form(?array $location, ?string $error = null, ?array $draft = null, int $status = 200): Response
    {
        $kind = LocationKind::normalize((string)($draft['kind'] ?? $location['kind'] ?? LocationKind::ROOM));
        $parentId = (int)($draft['parent_id'] ?? $location['parent_id'] ?? 0);
        $parent = $parentId > 0 ? $this->locations->find($parentId) : null;
        $objectId = (int)($location['object_id'] ?? 0);
        $classId = (int)($location['class_id'] ?? 0);
        $roomObject = $objectId > 0 ? $this->objects->find($objectId) : null;
        $roomClass = $classId > 0 ? $this->classes->find($classId) : null;
        $roomState = $location !== null && (string)$location['kind'] === LocationKind::ROOM
            ? $this->activity->stateForLocation($location) : null;
        $sources = $location !== null && (string)$location['kind'] === LocationKind::ROOM
            ? $this->activity->sources($location) : [];

        $content = $this->view->make('pages.locations.form', [
            'location' => $location,
            'draft' => $draft,
            'title' => (string)($draft['title'] ?? $location['title'] ?? ''),
            'priority' => (int)($draft['priority'] ?? $location['priority'] ?? 0),
            'kind' => $kind,
            'parentId' => (int)($draft['parent_id'] ?? $location['parent_id'] ?? 0),
            'parent' => $parent,
            'activityTimeout' => (int)($draft['activity_timeout'] ?? $location['activity_timeout'] ?? RoomProvisioner::DEFAULT_ACTIVITY_TIMEOUT),
            'kinds' => LocationKind::labels(),
            'parents' => $this->locations->parentOptions(),
            'roomObject' => $roomObject,
            'roomClass' => $roomClass,
            'roomState' => $roomState,
            'sources' => $sources,
            'objects' => $location === null ? [] : $this->objects->all('', 0, (int)$location['id']),
            'error' => $error ?? $this->pullError(),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(
            new PanelPage($location === null ? 'Новая локация' : 'Локация: ' . (string)$location['title'], $content, 'locations'),
            $status
        );
    }

    private function pullError(): ?string
    {
        $error = $this->session->get(self::FLASH_ERROR);
        if ($error === null) {
            return null;
        }
        $this->session->forget(self::FLASH_ERROR);

        return is_string($error) && $error !== '' ? $error : null;
    }
}
