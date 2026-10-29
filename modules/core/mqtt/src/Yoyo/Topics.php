<?php

declare(strict_types=1);

namespace LaIOT\Mqtt\Yoyo;

use Clickfwd\Yoyo\Component;
use LaIOT\Services\Auth\Authorization;
use LaIOT\Http\Session\SessionStore;
use LaIOT\Services\Locations\LocationRepository;
use LaIOT\Services\Modules\ModuleManager;
use LaIOT\Mqtt\RuntimeState;
use LaIOT\Mqtt\Settings;
use LaIOT\Mqtt\TopicRepository;
use Throwable;

/**
 * Список топиков MQTT с фильтрами, группами и живым обновлением.
 *
 * Всё состояние (поиск, группа, режим, сортировка, страница) живёт в
 * компоненте, поэтому таблица перерисовывается сервером через `/yoyo`, без
 * GET-перезагрузки и отдельного JSON-эндпоинта. Повторный рендер по событию
 * `mqtt-refresh` обновляет значения, не трогая фильтры.
 *
 * 2026-10-27 — alpha-таблица MQTT переведена на Yoyo.
 */
final class Topics extends Component
{
    public string $search = '';

    public string $groupBy = 'device';

    public string $group = '';

    public string $linked = '';

    public string $sort = 'updated';

    public string $page = '1';

    public string $locationId = '0';

    public string $groupSearch = '';

    /** @var list<string> */
    protected $props = ['search', 'groupBy', 'group', 'linked', 'sort', 'page', 'locationId', 'groupSearch'];

    /** @var array<string, string> */
    protected $listeners = ['mqtt-refresh' => 'refreshValues'];

    private ?TopicRepository $topics = null;

    private ?Settings $settings = null;

    private ?RuntimeState $runtime = null;

    private ?LocationRepository $locations = null;

    private ?SessionStore $session = null;

    private bool $enabled = true;

    public function mount(
        TopicRepository $topics,
        Settings $settings,
        RuntimeState $runtime,
        LocationRepository $locations,
        SessionStore $session,
        ModuleManager $modules,
        Authorization $authorization
    ): void {
        $this->enabled = $modules->get('mqtt') !== null && $authorization->can('settings.manage');
        $this->topics = $topics;
        $this->settings = $settings;
        $this->runtime = $runtime;
        $this->locations = $locations;
        $this->session = $session;
        $this->groupBy = $this->groupBy === 'path' ? 'path' : 'device';
        $this->sort = in_array($this->sort, ['updated', 'path', 'title', 'value'], true) ? $this->sort : 'updated';
        $this->linked = in_array($this->linked, ['', '1', '0'], true) ? $this->linked : '';
        $this->page = (string)max(1, (int)$this->page);
        $this->locationId = (string)max(0, (int)$this->locationId);
    }

    public function selectGroup(): void
    {
        $this->group = (string)$this->getQueryParam('groupKey', '');
        $this->page = '1';
    }

    public function setMode(): void
    {
        $this->groupBy = $this->getQueryParam('mode', 'device') === 'path' ? 'path' : 'device';
        $this->group = '';
        $this->page = '1';
    }

    public function clearGroup(): void
    {
        $this->group = '';
        $this->page = '1';
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->group = '';
        $this->groupSearch = '';
        $this->linked = '';
        $this->locationId = '0';
        $this->sort = 'updated';
        $this->page = '1';
    }

    public function paginate(): void
    {
        $this->page = (string)max(1, (int)$this->getQueryParam('page', 1));
    }

    public function refreshValues(): void
    {
        // Действие без состояния: повторный render читает свежие значения.
    }

    public function render(): mixed
    {
        if (!$this->enabled) {
            return '';
        }
        $filters = [
            'q' => trim($this->search),
            'group' => trim($this->group),
            'group_by' => $this->groupBy,
            'linked' => $this->linked,
            'sort' => $this->sort,
            'page' => max(1, (int)$this->page),
            'location_id' => max(0, (int)$this->locationId),
        ];
        $result = $this->topics?->search($filters) ?? [
            'rows' => [],
            'total' => 0,
            'page' => 1,
            'pages' => 1,
            'per_page' => 20,
        ];
        $this->page = (string)(int)$result['page'];
        $stats = $this->topics?->stats() ?? [
            'total' => 0, 'linked' => 0, 'unlinked' => 0, 'active' => 0, 'devices' => 0, 'last_update' => '',
        ];
        $groups = $this->topics?->groups($this->groupBy) ?? ['groups' => [], 'limited' => false];
        if (trim($this->groupSearch) !== '') {
            $needle = mb_strtolower(trim($this->groupSearch));
            $groups['groups'] = array_values(array_filter(
                $groups['groups'],
                static fn (array $group): bool => str_contains(mb_strtolower((string)$group['title']), $needle)
            ));
        }

        $locations = [];
        try {
            foreach ($this->locations?->all() ?? [] as $location) {
                $locations[] = ['id' => (int)$location['id'], 'title' => (string)$location['title']];
            }
        } catch (Throwable) {
            $locations = [];
        }

        $selectedTitle = 'Все топики';
        $selectedMeta = 'Весь журнал брокера';
        if ($this->group === '__unlinked__') {
            $selectedTitle = 'Без привязки';
            $selectedMeta = 'Топики без объекта, свойства и метода';
        } elseif ($this->group !== '') {
            $selectedTitle = $this->group;
            $selectedMeta = $this->groupBy === 'path' ? 'Ветка пути' : 'Устройство';
        }

        return $this->view('mqtt::yoyo.topics', [
            'search' => $this->search,
            'groupBy' => $this->groupBy,
            'group' => $this->group,
            'groupSearch' => $this->groupSearch,
            'linked' => $this->linked,
            'sort' => $this->sort,
            'page' => $this->page,
            'locationId' => $this->locationId,
            'result' => $result,
            'stats' => $stats,
            'groups' => $groups,
            'locations' => $locations,
            'status' => $this->runtime?->status() ?? [],
            'broker' => (string)($this->settings?->string('host') ?? '') . ':' . (string)($this->settings?->int('port') ?? ''),
            'selectedTitle' => $selectedTitle,
            'selectedMeta' => $selectedMeta,
            'csrf' => (string)($this->session?->csrfToken() ?? ''),
        ]);
    }
}
