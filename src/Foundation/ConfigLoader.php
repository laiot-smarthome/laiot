<?php

declare(strict_types=1);

namespace MajorDoMo\Foundation;

/**
 * Сборка конфигурации из config/*.php.
 *
 * app.php остаётся базой; отдельные файлы добавляют namespace-ключи
 * (`cache`, `queue`, `logging`), чтобы не держать всё в одном массиве.
 */
final class ConfigLoader
{
    /**
     * @return array<string, mixed>
     */
    public static function load(string $root): array
    {
        $base = require $root . '/config/app.php';
        if (!is_array($base)) {
            return [];
        }

        foreach (['cache', 'queue', 'logging'] as $section) {
            $file = $root . '/config/' . $section . '.php';
            if (is_file($file)) {
                $value = require $file;
                if (is_array($value)) {
                    $base[$section] = $value;
                }
            }
        }

        return $base;
    }
}
