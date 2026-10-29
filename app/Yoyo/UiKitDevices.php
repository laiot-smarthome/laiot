<?php

declare(strict_types=1);

namespace LaIOT\Yoyo;

use Clickfwd\Yoyo\Component;

/**
 * Изолированный пример серверной фильтрации в справочнике компонентов.
 *
 * 2026-10-27 — добавлен пример обновления таблицы через Yoyo.
 */
final class UiKitDevices extends Component
{
    public string $search = '';

    /** @var list<string> */
    protected $props = ['search'];

    public function render(): mixed
    {
        $items = [
            ['name' => 'Основной свет', 'room' => 'Кухня', 'state' => 'Онлайн'],
            ['name' => 'Термостат', 'room' => 'Гостиная', 'state' => 'Онлайн'],
            ['name' => 'Датчик движения', 'room' => 'Прихожая', 'state' => 'Офлайн'],
        ];
        $search = mb_strtolower(trim($this->search));
        $rows = array_values(array_filter($items, static fn (array $item): bool => $search === '' || mb_stripos(implode(' ', $item), $search) !== false));

        return $this->view('ui-kit-devices', ['rows' => $rows, 'search' => $this->search]);
    }
}
