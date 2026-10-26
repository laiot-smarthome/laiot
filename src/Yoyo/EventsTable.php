<?php

declare(strict_types=1);

namespace MajorDoMo\Yoyo;

use Clickfwd\Yoyo\Component;
use MajorDoMo\Events\PropertyEventRepository;

/**
 * Журнал событий свойства как Yoyo-компонент.
 *
 * Поиск и фильтры — состояния компонента: страница не перезагружается,
 * отдельного JSON-роута нет. Компонент сам запрашивает репозиторий и
 * перерисовывает таблицу по действию.
 *
 * 2026-10-27 — первый раздел, переведённый на Yoyo-таблицу вместо GET-фильтров.
 */
final class EventsTable extends Component
{
    public string $search = '';

    public string $type = '';

    public string $status = '';

    /** @var list<string> */
    protected $props = ['search', 'type', 'status'];

    private ?PropertyEventRepository $events = null;

    public function mount(PropertyEventRepository $events): void
    {
        $this->events = $events;
    }

    public function reset(): void
    {
        $this->search = '';
        $this->type = '';
        $this->status = '';
    }

    public function render(): mixed
    {
        $typeLabels = [
            'property.changed' => 'Свойство',
            'handler.method' => 'Обработчик',
        ];
        $statusLabels = [
            'changed' => 'Значение изменено',
            'stopped' => 'Цикл остановлен',
            'running' => 'Выполняется',
            'ok' => 'Обработчик выполнен',
            'error' => 'Ошибка обработчика',
            'missing' => 'Метод не найден',
            'skipped' => 'Обработчик пропущен',
        ];
        $statusTones = [
            'changed' => 'info',
            'stopped' => 'warning',
            'running' => 'info',
            'ok' => 'success',
            'error' => 'danger',
            'missing' => 'danger',
            'skipped' => 'warning',
        ];

        $rows = $this->events?->latest(200, trim($this->search), $this->type, $this->status) ?? [];

        return $this->view('events-table', [
            'rows' => $rows,
            'search' => $this->search,
            'type' => $this->type,
            'status' => $this->status,
            'typeLabels' => $typeLabels,
            'statusLabels' => $statusLabels,
            'statusTones' => $statusTones,
        ]);
    }
}
