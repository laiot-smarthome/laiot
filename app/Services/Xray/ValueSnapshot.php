<?php

declare(strict_types=1);

namespace LaIOT\Services\Xray;

use SplObjectStorage;

/**
 * Ограниченный снимок произвольного PHP-значения без вызова пользовательских методов.
 * 2026-10-30 — типы, циклические ссылки, бинарные строки и общий бюджет узлов.
 */
final class ValueSnapshot
{
    /** @return array<string, mixed> */
    public function capture(mixed $value): array
    {
        $budget = 300;

        return $this->walk($value, new SplObjectStorage(), 0, $budget);
    }

    /**
     * @param SplObjectStorage<object, null> $seen
     * @return array<string, mixed>
     */
    private function walk(mixed $value, SplObjectStorage $seen, int $depth, int &$budget): array
    {
        $type = get_debug_type($value);
        if (--$budget < 0 || $depth >= 8) {
            return ['type' => $type, 'value' => '… предел размера или глубины'];
        }
        if (is_string($value)) {
            $binary = !mb_check_encoding($value, 'UTF-8');

            return ['type' => $binary ? 'binary' : 'string', 'value' => $binary
                ? bin2hex(substr($value, 0, 1024))
                : mb_substr($value, 0, 4096), 'truncated' => ($binary ? strlen($value) : mb_strlen($value)) > ($binary ? 1024 : 4096)];
        }
        if (is_float($value) && !is_finite($value)) {
            return ['type' => $type, 'value' => (string)$value];
        }
        if (!is_array($value) && !is_object($value)) {
            return ['type' => $type, 'value' => is_resource($value) ? get_resource_type($value) : $value];
        }
        if (is_object($value)) {
            if ($seen->contains($value)) {
                return ['type' => $type, 'value' => '↩ повторная ссылка'];
            }
            $seen->attach($value);
            // __debugInfo, JsonSerializable и итераторы могут иметь побочные эффекты.
            $items = get_mangled_object_vars($value);
        } else {
            $items = $value;
        }
        $children = [];
        foreach ($items as $key => $item) {
            if ($budget <= 0) {
                break;
            }
            $children[] = ['key' => str_replace("\0", ':', (string)$key), 'node' => $this->walk($item, $seen, $depth + 1, $budget)];
        }

        return ['type' => $type, 'children' => $children, 'count' => count($items), 'truncated' => count($children) < count($items)];
    }
}
