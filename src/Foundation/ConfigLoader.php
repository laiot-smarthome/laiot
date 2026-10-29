<?php

declare(strict_types=1);

namespace MajorDoMo\Foundation;

/**
 * Сборка конфигурации из config/*.php.
 *
 * app.php остаётся базой; отдельные файлы добавляют namespace-ключи
 * (`cache`, `queue`, `logging`, `retention`, `providers`), чтобы не держать всё
 * в одном массиве.
 *
 * Изменения:
 * 2026-10-29 — настройки окна повторов уведомлений вынесены в отдельную секцию.
 * 2026-10-28 — отдельная конфигурация шифрования и файлового хранилища.
 * 2026-10-27 — секция retention.
 * 2026-10-26 — список сервис-провайдеров загружается из config/providers.php.
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

        foreach (['cache', 'queue', 'logging', 'retention', 'encryption', 'files', 'notifications', 'providers'] as $section) {
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
