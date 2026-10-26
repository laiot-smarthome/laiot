<?php

declare(strict_types=1);

namespace MajorDoMo\Yoyo;

use Clickfwd\Yoyo\Component;
use MajorDoMo\History\PropertyHistoryService;

/**
 * История свойства как Yoyo-компонент.
 *
 * Кнопка-иконка стоит в строке значения; по клику компонент сам перерисовывает
 * себя в drawer и меняет период без отдельного JSON-эндпоинта. Данные считает
 * PropertyHistoryService, график рисует ленивый чанк ApexCharts по data-атрибуту.
 *
 * 2026-10-27 — компонент заменил отдельный /api/v1/panel/history.
 */
final class PropertyHistory extends Component
{
    public int $objectId = 0;

    public int $propertyId = 0;

    public string $title = '';

    public string $range = '24h';

    public bool $open = false;

    /** @var list<string> */
    protected $props = ['objectId', 'propertyId', 'title', 'range', 'open'];

    private ?PropertyHistoryService $history = null;

    public function mount(
        PropertyHistoryService $history,
        int $objectId = 0,
        int $propertyId = 0,
        string $title = ''
    ): void {
        $this->history = $history;
        $this->objectId = $objectId;
        $this->propertyId = $propertyId;
        $this->title = $title;
    }

    public function open(): void
    {
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function setRange(string $range): void
    {
        if (in_array($range, ['24h', '7d', '30d', 'all'], true)) {
            $this->range = $range;
        }
    }

    public function render(): mixed
    {
        $data = $this->open && $this->history !== null
            ? $this->history->for($this->objectId, $this->propertyId, $this->range)
            : null;

        return $this->view('property-history', [
            'data' => $data,
            'title' => $this->title,
            'range' => $this->range,
        ]);
    }
}
