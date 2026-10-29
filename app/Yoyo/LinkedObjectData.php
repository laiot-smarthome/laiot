<?php

declare(strict_types=1);

namespace LaIOT\Yoyo;

use Clickfwd\Yoyo\Component;
use Illuminate\Container\Container;
use LaIOT\Services\LinkedObject\LinkedObjectService;

/**
 * Серверные варианты редактора связей; локальный выбор остаётся в Alpine.
 *
 * Отдельная область обновления сохраняет введённые значения и фокус полей.
 * 2026-10-28 — чтение объектов и контекста через общий маршрут Yoyo.
 */
final class LinkedObjectData extends Component
{
    public string $query = '';
    public string $object = '';
    public int $sequence = 0;

    /** @var list<string> */
    protected $props = ['query', 'object', 'sequence'];

    public function render(): mixed
    {
        // Пустой редактор в UI Kit не требует БД; данные загружаются при открытии.
        $service = $this->sequence > 0 || $this->object !== ''
            ? Container::getInstance()->make(LinkedObjectService::class) : null;

        return $this->view('linked-object-data', [
            'payload' => [
                'sequence' => $this->sequence,
                'object' => $this->object,
                'objects' => $service?->suggestions($this->query) ?? [],
                'context' => $service?->context($this->object),
            ],
        ]);
    }
}
