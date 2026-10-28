<?php

declare(strict_types=1);

namespace MajorDoMo\Zigbee\Yoyo;

use Clickfwd\Yoyo\Component;
use MajorDoMo\Auth\Authorization;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Locations\LocationRepository;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Zigbee\Repository;
use MajorDoMo\Zigbee\Support\PanelData;

/**
 * Список устройств Zigbee с поиском, вкладками и комнатами.
 *
 * Фильтры и страница живут в компоненте, таблица перерисовывается сервером
 * через `/yoyo`; отдельного JSON-эндпоинта нет. Форма добавления остаётся
 * обычным POST с CSRF: это изменяющая операция, а не состояние интерфейса.
 *
 * 2026-10-28 — alpha-таблица Zigbee переведена на Yoyo.
 */
final class Devices extends Component
{
    public string $search = '';

    public string $status = '';

    public string $bridge = '0';

    public string $location = '0';

    public string $available = '';

    public string $sort = 'name';

    public string $page = '1';

    /** @var list<string> */
    protected $props = ['search', 'status', 'bridge', 'location', 'available', 'sort', 'page'];

    private ?Repository $repository = null;

    private ?LocationRepository $locations = null;

    private ?SessionStore $session = null;

    private bool $enabled = true;

    public function mount(
        Repository $repository,
        LocationRepository $locations,
        SessionStore $session,
        ModuleManager $modules,
        Authorization $authorization
    ): void {
        $this->enabled = $modules->get('zigbee') !== null && $authorization->can('settings.manage');
        $this->repository = $repository;
        $this->locations = $locations;
        $this->session = $session;
        $this->status = in_array($this->status, ['added', 'discovered'], true) ? $this->status : '';
        $this->available = in_array($this->available, ['online', 'offline'], true) ? $this->available : '';
        $this->sort = in_array($this->sort, ['name', 'seen', 'model'], true) ? $this->sort : 'name';
        $this->page = (string)max(1, (int)$this->page);
        $this->bridge = (string)max(0, (int)$this->bridge);
        $this->location = (string)max(0, (int)$this->location);
    }

    public function setStatus(): void
    {
        $status = (string)$this->getQueryParam('status', '');
        $this->status = in_array($status, ['added', 'discovered'], true) ? $status : '';
        $this->page = '1';
    }

    public function setAvailable(): void
    {
        $value = (string)$this->getQueryParam('available', '');
        $this->available = in_array($value, ['online', 'offline'], true) ? $value : '';
        $this->page = '1';
    }

    public function paginate(): void
    {
        $this->page = (string)max(1, (int)$this->getQueryParam('page', 1));
    }

    public function refresh(): void
    {
        // Действие без состояния: повторный render читает свежие данные.
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->bridge = '0';
        $this->location = '0';
        $this->available = '';
        $this->sort = 'name';
        $this->page = '1';
    }

    public function render(): mixed
    {
        if (!$this->enabled) {
            return '';
        }
        $filters = [
            'q' => trim($this->search),
            'status' => $this->status,
            'bridge_id' => max(0, (int)$this->bridge),
            'location_id' => max(0, (int)$this->location),
            'available' => $this->available,
            'sort' => $this->sort,
            'page' => max(1, (int)$this->page),
        ];
        $result = $this->repository?->searchDevices($filters) ?? [
            'rows' => [],
            'total' => 0,
            'page' => 1,
            'pages' => 1,
            'per_page' => 24,
        ];
        $this->page = (string)(int)$result['page'];
        $ids = array_map(static fn (array $row): int => (int)$row['id'], $result['rows']);
        $bridges = $this->repository?->bridges() ?? [];
        $bridgeOptions = ['0' => 'Все экземпляры'];
        foreach ($bridges as $item) {
            $bridgeOptions[(string)$item['id']] = (string)$item['title'];
        }
        $rooms = $this->locations !== null ? PanelData::rooms($this->locations) : [];
        $roomOptions = ['0' => 'Любая комната'];
        $assignOptions = ['0' => 'Без комнаты'];
        foreach ($rooms as $room) {
            $roomOptions[(string)$room['id']] = (string)$room['title'];
            $assignOptions[(string)$room['id']] = (string)$room['title'];
        }

        return $this->view('zigbee::yoyo.devices', [
            'search' => $this->search,
            'status' => $this->status,
            'bridge' => $this->bridge,
            'location' => $this->location,
            'available' => $this->available,
            'sort' => $this->sort,
            'page' => $this->page,
            'result' => $result,
            'counts' => $this->repository?->counts() ?? [],
            'bridges' => $bridges,
            'bridgeOptions' => $bridgeOptions,
            'roomOptions' => $roomOptions,
            'assignOptions' => $assignOptions,
            'rooms' => $rooms,
            'states' => $this->repository?->healthStates($ids) ?? [],
            'health' => $this->repository?->healthSettings() ?? [],
            'csrf' => (string)($this->session?->csrfToken() ?? ''),
        ]);
    }
}
