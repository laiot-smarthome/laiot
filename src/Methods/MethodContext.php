<?php

declare(strict_types=1);

namespace MajorDoMo\Methods;

use Closure;
use MajorDoMo\History\HistoryQuery;
use MajorDoMo\Notifications\NotificationService;
use MajorDoMo\Properties\PropertyValueService;

/**
 * `$this` внутри пользовательского метода.
 *
 * Раньше код метода выполнялся статическим замыканием, и `$this` был недоступен:
 * приходилось звать глобальные `gg()`, `sg()`, `callMethod()`. Теперь у метода
 * есть контекст объекта: свойства читаются и пишутся по короткому имени, вызов
 * другого метода и история — рядом, без склейки строк «Объект.Свойство».
 *
 * Сюда же приходит `$params` — для совместимости старый массив остаётся
 * доступен в коде метода как `$params`, а `$this->param()` — короткий доступ.
 *
 * 2026-10-27 — контекст создан вместо статического замыкания без `$this`.
 */
final readonly class MethodContext
{
    /**
     * @param array<string, mixed> $params
     * @param Closure(string, array<string, mixed>): mixed $call
     */
    public function __construct(
        private string $objectTitle,
        private int $objectId,
        private string $methodTitle,
        private array $params,
        private PropertyValueService $values,
        private NotificationService $notifications,
        private Closure $call
    ) {
    }

    public function title(): string
    {
        return $this->objectTitle;
    }

    public function id(): int
    {
        return $this->objectId;
    }

    public function method(): string
    {
        return $this->methodTitle;
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return $this->params;
    }

    public function param(string $name, mixed $default = null): mixed
    {
        return $this->params[$name] ?? $default;
    }

    /** Значение свойства этого объекта; null, если свойства нет. */
    public function get(string $property): ?string
    {
        return $this->values->get($this->objectTitle, $property);
    }

    /** Запись свойства этого объекта через общий конвейер значения и событий. */
    public function set(string $property, mixed $value, string $source = 'method'): bool
    {
        return $this->values->set($this->objectTitle, $property, $value, $source);
    }

    /** История свойства этого объекта: `->from('-1 day')->avg()`. */
    public function history(string $property): ?HistoryQuery
    {
        return $this->values->query($this->objectTitle, $property);
    }

    /**
     * Вызвать другой метод этого объекта.
     *
     * @param array<string, mixed> $params
     */
    public function call(string $method, array $params = []): mixed
    {
        return ($this->call)($method, $params);
    }

    /**
     * @param array{key?:string,title?:string,details?:string,url?:string,label?:string} $options
     * @return array{status:bool,id?:int,error?:string}
     */
    public function notify(string $message, string $type = 'info', array $options = []): array
    {
        return $this->notifications->send('method', $message, $type, $options + [
            'label' => $this->objectTitle . '.' . $this->methodTitle,
        ]);
    }
}
