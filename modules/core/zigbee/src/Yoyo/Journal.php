<?php

declare(strict_types=1);

namespace LaIOT\Zigbee\Yoyo;

use Clickfwd\Yoyo\Component;
use LaIOT\Services\Auth\Authorization;
use LaIOT\Services\Modules\ModuleManager;
use LaIOT\Zigbee\Repository;

/**
 * Журнал Zigbee с фильтрами и страницами.
 *
 * Читает только свои записи через Repository; форма очистки — обычный POST
 * на странице, а не действие Yoyo, потому что меняет данные навсегда.
 *
 * 2026-10-28 — журнал модуля на Yoyo.
 */
final class Journal extends Component
{
    public string $search = '';

    public string $level = '';

    public string $bridge = '0';

    public string $page = '1';

    /** @var list<string> */
    protected $props = ['search', 'level', 'bridge', 'page'];

    private ?Repository $repository = null;

    private bool $enabled = true;

    public function mount(Repository $repository, ModuleManager $modules, Authorization $authorization): void
    {
        $this->enabled = $modules->get('zigbee') !== null && $authorization->can('settings.manage');
        $this->repository = $repository;
        $this->level = in_array($this->level, ['info', 'warning', 'error'], true) ? $this->level : '';
        $this->bridge = (string)max(0, (int)$this->bridge);
        $this->page = (string)max(1, (int)$this->page);
    }

    public function setLevel(): void
    {
        $level = (string)$this->getQueryParam('level', '');
        $this->level = in_array($level, ['info', 'warning', 'error'], true) ? $level : '';
        $this->page = '1';
    }

    public function paginate(): void
    {
        $this->page = (string)max(1, (int)$this->getQueryParam('page', 1));
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->level = '';
        $this->bridge = '0';
        $this->page = '1';
    }

    public function render(): mixed
    {
        if (!$this->enabled) {
            return '';
        }
        $result = $this->repository?->events([
            'q' => trim($this->search),
            'level' => $this->level,
            'bridge_id' => max(0, (int)$this->bridge),
            'page' => max(1, (int)$this->page),
            'per_page' => 50,
        ]) ?? ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 50];
        $this->page = (string)(int)$result['page'];

        return $this->view('zigbee::yoyo.journal', [
            'search' => $this->search,
            'level' => $this->level,
            'bridge' => $this->bridge,
            'page' => $this->page,
            'result' => $result,
            'bridges' => $this->repository?->bridges() ?? [],
        ]);
    }
}
