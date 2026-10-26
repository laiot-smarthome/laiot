<?php

declare(strict_types=1);

namespace MajorDoMo\Events;

/**
 * Контекст одной цепочки изменений свойств.
 *
 * Пока обработчик OnChange выполняется, его свойство уже лежит в цепочке.
 * Если вложенная запись снова приходит к этому свойству (A → метод → B →
 * метод → A), цепочка считается закольцованной: значение сохраняется, но
 * следующий обработчик не вызывается. Так автоматизация не уходит в
 * бесконечный цикл, а данные датчика не теряются.
 *
 * 2026-10-26 — класс создан для нового ядра MyDoMo.
 */
final class PropertyEventChain
{
    /** Предел вложенности: дальше цепочка считается ошибочной и обрывается. */
    public const MAX_DEPTH = 32;

    /** @var list<string> */
    private array $tokens = [];

    /** @param string $objectTitle Название объекта. */
    public function token(string $objectTitle, string $propertyTitle): string
    {
        return $objectTitle . '.' . $propertyTitle;
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

    /**
     * @return list<string>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }
}
