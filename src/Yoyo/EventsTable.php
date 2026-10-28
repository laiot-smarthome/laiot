<?php

declare(strict_types=1);

namespace MajorDoMo\Yoyo;

use Clickfwd\Yoyo\Component;
use MajorDoMo\Events\EventChainService;

/**
 * Список цепочек и отдельный экран действий на общих Yoyo-компонентах.
 *
 * 2026-10-28 — одна строка на цепочку; детали сохраняют поиск и страницу списка.
 * 2026-10-27 — первый раздел, переведённый на Yoyo-таблицу вместо GET-фильтров.
 */
final class EventsTable extends Component
{
    public string $search = '';

    public string $type = '';

    public string $status = '';

    public int $selectedId = 0;

    public int $page = 1;

    /** @var list<string> Фильтры берутся из полей, чтобы hx-vals не перекрывал новый ввод. */
    protected $props = ['selectedId', 'page'];

    private EventChainService $chains;

    public function mount(EventChainService $chains): void
    {
        $this->chains = $chains;
    }

    public function open(int $id): void
    {
        $this->selectedId = max(0, $id);
    }

    public function back(): void
    {
        $this->selectedId = 0;
    }

    public function filter(): void
    {
        $this->page = 1;
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function reset(): void
    {
        $this->search = '';
        $this->type = '';
        $this->status = '';
        $this->page = 1;
    }

    public function render(): mixed
    {
        $context = ['q' => $this->search, 'type' => $this->type, 'status' => $this->status, 'page' => $this->page];
        $data = [
            'search' => $this->search, 'type' => $this->type, 'status' => $this->status, 'page' => $this->page,
            'context' => $context,
            'typeLabels' => ['property.changed' => 'Свойство', 'handler.method' => 'Обработчик', 'module.delivery' => 'Доставка модулю'],
            'statusLabels' => [
                'changed' => 'Значение изменено', 'stopped' => 'Цикл остановлен', 'running' => 'Выполняется',
                'ok' => 'Выполнено', 'error' => 'Ошибка', 'missing' => 'Метод не найден', 'skipped' => 'Пропущено',
            ],
            'chainLabels' => ['ok' => 'Завершена', 'error' => 'Ошибка', 'running' => 'Выполняется', 'stopped' => 'Цикл остановлен', 'skipped' => 'Пропущена'],
            'chainTones' => ['ok' => 'success', 'error' => 'danger', 'running' => 'info', 'stopped' => 'warning', 'skipped' => 'neutral'],
        ];
        if ($this->selectedId > 0) {
            return $this->view('event-chain-detail', $data + [
                'detail' => $this->chains->detail($this->selectedId),
                'selectedId' => $this->selectedId,
            ]);
        }
        $listing = $this->chains->listing(trim($this->search), $this->type, $this->status, $this->page);

        return $this->view('events-table', $data + ['chains' => $listing['items'], 'more' => $listing['more']]);
    }
}
