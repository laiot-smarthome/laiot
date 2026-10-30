<?php

declare(strict_types=1);

namespace LaIOT\Yoyo;

use Clickfwd\Yoyo\Component;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LaIOT\Services\Auth\Authorization;
use LaIOT\Services\Xray\XrayJournal;
use LaIOT\Services\Xray\XrayService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * X-ray: обновляемая лента, замороженный архив, таймеры и процессы.
 * 2026-10-30 — общий Yoyo-вход, серверная валидация и авторизация действий.
 */
final class Xray extends Component
{
    public string $tab = 'debug';
    public string $search = '';
    public bool $paused = false;
    public int $page = 1;
    public int $snapshot = 0;
    public int $selectedId = 0;

    /** @var list<string> Поиск берётся из поля формы, остальные состояния — из компонента. */
    protected $props = ['tab', 'paused', 'page', 'snapshot', 'selectedId'];

    private XrayJournal $journal;
    private XrayService $service;
    private Authorization $authorization;
    private Request $httpRequest;
    private Factory $validation;

    public function mount(XrayJournal $journal, XrayService $service, Authorization $authorization, Request $request, Factory $validation): void
    {
        $this->journal = $journal;
        $this->service = $service;
        $this->authorization = $authorization;
        $this->httpRequest = $request;
        $this->validation = $validation;
    }

    public function changeTab(string $tab): void
    {
        $this->tab = $tab;
        $this->search = '';
        $this->selectedId = 0;
        $this->filter();
    }

    public function filter(): void
    {
        $this->page = 1;
        $this->snapshot = 0;
    }

    public function refresh(): void
    {
        if (!$this->paused && $this->page === 1 && $this->selectedId === 0) {
            $this->snapshot = 0;
        }
    }

    public function togglePause(): void
    {
        $this->paused = !$this->paused;
        if (!$this->paused) {
            $this->filter();
        }
    }

    public function nextPage(): void
    {
        $this->paused = true;
        $this->page = min(10000, $this->page + 1);
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function open(int $id): void
    {
        if (($this->journal->find($id)['kind'] ?? '') === 'property') {
            return;
        }
        $this->selectedId = max(0, $id);
    }

    public function back(): void
    {
        $this->selectedId = 0;
    }

    public function cancelTimer(int $id): void
    {
        $this->authorize(true);
        try {
            $this->service->cancelTimer($id);
            $this->dispatchBrowserEvent('md:notify', ['tone' => 'success', 'text' => 'Таймер отменён.']);
        } catch (Throwable $error) {
            $this->dispatchBrowserEvent('md:notify', ['tone' => 'danger', 'text' => $error->getMessage()]);
        }
    }

    public function control(string $kind, string $id, string $operation): void
    {
        $this->authorize(true);
        try {
            $this->service->control($kind, $id, $operation);
            $this->journal->append('control', $kind . ':' . $id, 'panel', ['operation' => $operation, 'user_id' => $this->authorization->user()['id'] ?? null]);
            $this->dispatchBrowserEvent('md:notify', ['tone' => 'success', 'text' => 'Команда процессу выполнена. Состояние обновится с heartbeat.']);
        } catch (Throwable $error) {
            $this->dispatchBrowserEvent('md:notify', ['tone' => 'danger', 'text' => $error->getMessage()]);
        }
    }

    public function render(): mixed
    {
        $this->authorize();
        $this->validation->make([
            'tab' => $this->tab, 'search' => $this->search, 'page' => $this->page,
            'snapshot' => $this->snapshot, 'selectedId' => $this->selectedId,
        ], [
            'tab' => [Rule::in(['property', 'debug', 'script', 'event', 'timers', 'cycles'])],
            'search' => ['string', 'max:191'], 'page' => ['integer', 'between:1,10000'],
            'snapshot' => ['integer', 'min:0'], 'selectedId' => ['integer', 'min:0'],
        ])->validate();
        if ($this->snapshot === 0 || (!$this->paused && $this->page === 1 && $this->selectedId === 0)) {
            $this->snapshot = $this->journal->maxId();
        }
        $rows = in_array($this->tab, ['timers', 'cycles'], true) ? []
            : $this->journal->listing($this->tab, trim($this->search), $this->snapshot, $this->page);

        return $this->view('xray', [
            'tabs' => ['debug' => 'Отладка', 'property' => 'Свойства', 'script' => 'Сценарии', 'event' => 'События', 'timers' => 'Таймеры', 'cycles' => 'Циклы'],
            'labels' => ['property' => 'Свойство', 'debug' => 'Отладка', 'script' => 'Сценарий', 'event' => 'Событие', 'control' => 'Управление'],
            'rows' => array_slice($rows, 0, 50), 'more' => count($rows) > 50,
            'detail' => $this->selectedId > 0 ? $this->journal->find($this->selectedId) : null,
            'timers' => $this->tab === 'timers' ? $this->service->timers(trim($this->search)) : [],
            'cycles' => $this->tab === 'cycles' ? $this->service->cycles() : [],
            'subscriptions' => $this->tab === 'event' ? $this->service->subscriptions() : [],
            'counts' => $this->journal->counts(), 'summary' => $this->service->summary(),
            'updatedAt' => date('H:i:s'),
        ]);
    }

    private function authorize(bool $post = false): void
    {
        if (!$this->authorization->can('settings.manage') || ($post && !$this->httpRequest->isMethod('POST'))) {
            throw new HttpException(403, 'Недостаточно прав или неверный метод запроса.');
        }
    }
}
