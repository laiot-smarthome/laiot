<?php

declare(strict_types=1);

namespace MajorDoMo\Worker;

use Closure;
use MajorDoMo\Modules\ModuleContext;

/**
 * Контекст одного прохода фоновой задачи.
 *
 * Метод модуля получает этот объект и не управляет циклом/интервалом.
 */
final readonly class BackgroundTaskContext
{
    /**
     * @param Closure(): bool $shouldStop
     */
    public function __construct(
        public ModuleContext $module,
        public string $task,
        public string $method,
        public float $startedAt,
        public int $timeout,
        private Closure $shouldStop
    ) {
    }

    public function shouldStop(): bool
    {
        return ($this->shouldStop)();
    }

    public function deadline(): float
    {
        return $this->startedAt + $this->timeout;
    }
}
