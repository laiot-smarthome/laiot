<?php

declare(strict_types=1);

namespace LaIOT\Yoyo;

use Clickfwd\Yoyo\Component;

/**
 * Тестовый Yoyo-компонент.
 *
 * Публичное свойство уходит в браузер, поэтому здесь нет секретов и
 * объектов. Это проверка связки Blade + Yoyo, а не боевой счётчик.
 *
 * @property int $count
 */
final class Counter extends Component
{
    /** @var int */
    public $count = 0;

    /** @var list<string> */
    protected $props = ['count'];

    public function increment(): void
    {
        $this->count++;
    }
}
