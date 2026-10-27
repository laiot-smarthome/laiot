<?php

declare(strict_types=1);

namespace MajorDoMo\Events;

/**
 * Контекст выполнения изменения свойства.
 *
 * Пока пользовательский метод или подписчик обрабатывает событие, сервис
 * записи знает:
 * - цепочку свойств, уже пройденных этим событием (защита от цикла);
 * - id шага журнала, к которому относятся вложенные записи;
 * - модуль, который сейчас обрабатывает изменение (его собственные записи
 *   не должны возвращаться ему эхом).
 *
 * Контекст живёт в процессе обработчика событий: вложенная запись внутри
 * метода или подписчика получает цепочку и родителя из него, а не из
 * глобального состояния запроса.
 *
 * 2026-10-27 — заменяет PropertyEventChain: к цепочке добавлены родитель
 *              журнала и текущий модуль.
 */
final class PropertyEventContext
{
    /** Предел вложенности: дальше цепочка считается ошибочной и обрывается. */
    public const MAX_DEPTH = 32;

    /** @var list<string> */
    private array $tokens = [];

    private int $parentEventId = 0;

    private ?string $module = null;

    /**
     * @param list<string> $tokens
     */
    public function enter(array $tokens = [], int $parentEventId = 0, ?string $module = null): void
    {
        $this->tokens = self::clean($tokens);
        $this->parentEventId = max(0, $parentEventId);
        $this->module = $module;
    }

    public function leave(): void
    {
        $this->tokens = [];
        $this->parentEventId = 0;
        $this->module = null;
    }

    /**
     * @return array{tokens:list<string>,parent:int,module:?string}
     */
    public function snapshot(): array
    {
        return [
            'tokens' => $this->tokens,
            'parent' => $this->parentEventId,
            'module' => $this->module,
        ];
    }

    /**
     * @param array{tokens:list<string>,parent:int,module:?string} $snapshot
     */
    public function restore(array $snapshot): void
    {
        $this->tokens = $snapshot['tokens'];
        $this->parentEventId = $snapshot['parent'];
        $this->module = $snapshot['module'];
    }

    public function token(string $objectTitle, string $propertyTitle): string
    {
        return $objectTitle . '.' . $propertyTitle;
    }

    /**
     * @return list<string>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    public function depth(): int
    {
        return count($this->tokens);
    }

    public function contains(string $token): bool
    {
        return in_array($token, $this->tokens, true);
    }

    public function push(string $token): void
    {
        if (!in_array($token, $this->tokens, true)) {
            $this->tokens[] = $token;
        }
    }

    public function pop(): void
    {
        array_pop($this->tokens);
    }

    public function parentEventId(): int
    {
        return $this->parentEventId;
    }

    public function module(): ?string
    {
        return $this->module;
    }

    /**
     * Цепочка с текущим свойством: она же попадает в задание и журнал.
     *
     * @return list<string>
     */
    public function nextChain(string $token): array
    {
        $chain = $this->tokens;
        if (!in_array($token, $chain, true)) {
            $chain[] = $token;
        }

        return $chain;
    }

    /**
     * @param array<int,mixed> $tokens
     * @return list<string>
     */
    private static function clean(array $tokens): array
    {
        $result = [];
        foreach ($tokens as $token) {
            if (!is_string($token) || $token === '' || in_array($token, $result, true)) {
                continue;
            }
            $result[] = $token;
            if (count($result) >= self::MAX_DEPTH) {
                break;
            }
        }

        return $result;
    }
}
