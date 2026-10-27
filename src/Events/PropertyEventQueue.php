<?php

declare(strict_types=1);

namespace MajorDoMo\Events;

use MajorDoMo\Queue\Queue;

/**
 * Маршрутизация заданий изменения свойства по полосам.
 *
 * Одно событие — одно задание. Полоса выбирается по паре «объект + свойство»,
 * поэтому все изменения одного свойства всегда попадают в одну очередь и
 * обрабатываются по порядку, а разные свойства можно развести по нескольким
 * процессам `core-worker events.<n>`.
 *
 * При одной полосе имя очереди остаётся прежним — `events`; при нескольких
 * это `events.0` … `events.N-1`. Количество полос задаётся в `config/queue.php`
 * (`events_lanes`) и не может измениться на лету без переноса заданий.
 *
 * 2026-10-27 — полосы созданы, чтобы тысячи независимых свойств обрабатывались
 *              параллельно без потери порядка внутри одного свойства.
 */
final readonly class PropertyEventQueue
{
    public const QUEUE = 'events';
    public const MAX_LANES = 64;

    private int $lanes;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private Queue $queue,
        array $config = []
    ) {
        $this->lanes = max(1, min(self::MAX_LANES, (int)($config['events_lanes'] ?? 1)));
    }

    public function lanes(): int
    {
        return $this->lanes;
    }

    /** Стабильная полоса свойства: одинаковая во всех процессах. */
    public function laneFor(int $objectId, int $propertyId): int
    {
        $hash = abs((int)crc32($objectId . ':' . $propertyId));

        return $hash % $this->lanes;
    }

    public function queueName(int $lane): string
    {
        if ($this->lanes === 1) {
            return self::QUEUE;
        }

        return self::QUEUE . '.' . max(0, min($this->lanes - 1, $lane));
    }

    /**
     * @return list<string>
     */
    public function queueNames(): array
    {
        if ($this->lanes === 1) {
            return [self::QUEUE];
        }

        $names = [];
        for ($lane = 0; $lane < $this->lanes; $lane++) {
            $names[] = $this->queueName($lane);
        }

        return $names;
    }

    public function push(PropertyChangedJob $job, int $objectId, int $propertyId): int
    {
        return $this->queue->pushJob($job, $this->queueName($this->laneFor($objectId, $propertyId)));
    }

    /**
     * Очередь доставки конкретному модулю: её обслуживает `worker <id>`.
     */
    public function moduleQueueName(string $moduleId): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $moduleId)) {
            throw new \InvalidArgumentException('Некорректный id модуля-получателя: ' . $moduleId);
        }

        return 'module.' . $moduleId;
    }

    /** Суммарное число готовых заданий всех полос. */
    public function pending(): int
    {
        $total = 0;
        foreach ($this->queueNames() as $name) {
            $total += $this->queue->stats($name)['pending'];
        }

        return $total;
    }
}
