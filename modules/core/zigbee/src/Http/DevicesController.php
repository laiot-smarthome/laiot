<?php

declare(strict_types=1);

namespace MajorDoMo\Zigbee\Http;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Locations\LocationRepository;
use MajorDoMo\Zigbee\Repository;
use MajorDoMo\Zigbee\Service;
use MajorDoMo\Zigbee\Support\PanelData;
use Symfony\Component\HttpFoundation\Response;

/**
 * Главная страница Zigbee: обнаруженные и добавленные устройства.
 *
 * Список живёт в Yoyo-компоненте `zigbee-devices` (поиск, вкладки, комнаты,
 * пагинация), а формы добавления/назначения комнаты — обычный POST с CSRF:
 * это изменяющая операция, а не состояние интерфейса.
 *
 * 2026-10-28 — страница вместо alpha `panel/zigbee.html`.
 */
final readonly class DevicesController extends Controller
{
    public function __construct(
        private Factory $view,
        private PanelRenderer $panel,
        private Repository $repository,
        private Service $service,
        private LocationRepository $locations,
        private SessionStore $session
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $filters = $this->filters($request);
        $result = $this->repository->searchDevices($filters);
        $content = $this->view->make('zigbee::index', [
            'result' => $result,
            'counts' => $this->repository->counts(),
            'bridges' => $this->repository->bridges(),
            'rooms' => PanelData::rooms($this->locations),
            'csrf' => $this->session->csrfToken(),
            'filters' => $filters,
            'health' => $this->repository->healthSettings(),
        ])->render();

        return $this->panel->render(new PanelPage('Zigbee', $content, 'zigbee'));
    }

    /**
     * Добавляет выбранные устройства и/или назначает им комнату.
     */
    public function configure(Request $request): Response
    {
        $ids = $request->input('ids', []);
        $ids = is_array($ids) ? array_map('intval', $ids) : [];
        $adopt = $request->boolean('adopt');
        $locationId = max(0, (int)$request->input('location_id', 0));
        $title = trim((string)$request->input('title', ''));
        try {
            $count = $this->service->configureDevices(
                $ids,
                $locationId,
                $adopt,
                count($ids) === 1 && $title !== '' ? $title : null
            );
        } catch (InvalidArgumentException $error) {
            return $this->panel->redirect('/module/zigbee', $error->getMessage(), 'danger');
        }

        return $this->panel->redirect(
            '/module/zigbee?status=' . ($adopt ? 'added' : 'discovered'),
            $adopt ? 'Добавлено устройств: ' . $count : 'Комната назначена: ' . $count
        );
    }

    /**
     * «Добавить все найденные» с текущими фильтрами: до 500 устройств за раз.
     */
    public function configureAll(Request $request): Response
    {
        $filters = $this->filters($request);
        $filters['status'] = 'discovered';
        $ids = [];
        for ($page = 1; $page <= 5; $page++) {
            $filters['page'] = $page;
            $filters['per_page'] = 100;
            $result = $this->repository->searchDevices($filters);
            foreach ($result['rows'] as $row) {
                $ids[] = (int)$row['id'];
            }
            if ($page >= $result['pages']) {
                break;
            }
        }
        if ($ids === []) {
            return $this->panel->redirect('/module/zigbee?status=discovered', 'Нет обнаруженных устройств для добавления.', 'warning');
        }
        try {
            $count = $this->service->configureDevices(
                $ids,
                max(0, (int)$request->input('location_id', 0)),
                true
            );
        } catch (InvalidArgumentException $error) {
            return $this->panel->redirect('/module/zigbee', $error->getMessage(), 'danger');
        }

        return $this->panel->redirect('/module/zigbee?status=added', 'Добавлено устройств: ' . $count);
    }

    /**
     * @return array{q:string,status:string,bridge_id:int,location_id:int,available:string,sort:string,page:int}
     */
    private function filters(Request $request): array
    {
        // input() видит и query, и тело POST: «добавить все найденные»
        // приходит обычной формой и не должен терять фильтры.
        $status = (string)$request->input('status', '');
        $available = (string)$request->input('available', '');
        $sort = (string)$request->input('sort', 'name');

        return [
            'q' => trim((string)$request->input('q', '')),
            'status' => in_array($status, ['added', 'discovered'], true) ? $status : '',
            'bridge_id' => max(0, (int)$request->input('bridge', 0)),
            'location_id' => max(0, (int)$request->input('location', 0)),
            'available' => in_array($available, ['online', 'offline'], true) ? $available : '',
            'sort' => in_array($sort, ['name', 'seen', 'model'], true) ? $sort : 'name',
            'page' => max(1, (int)$request->input('page', 1)),
        ];
    }
}
