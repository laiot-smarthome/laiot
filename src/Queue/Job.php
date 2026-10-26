<?php

declare(strict_types=1);

namespace MajorDoMo\Queue;

/**
 * Типизированное задание очереди.
 *
 * Класс задания задаёт стабильное имя и payload; worker ищет обработчик
 * по этому имени, а не по произвольной строке из запроса.
 */
interface Job
{
    public function name(): string;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array;
}
