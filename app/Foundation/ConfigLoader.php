<?php

declare(strict_types=1);

namespace LaIOT\Foundation;

/**
 * Сборка конфигурации из config/*.php.
 *
 * app.php остаётся базой; отдельные файлы добавляют namespace-ключи
 * (`cache`, `queue`, `logging`, `retention`, `providers`), чтобы не держать всё
 * в одном массиве.
 *
 * Изменения:
 * 2026-10-29 — источник обновлений и версия из манифеста установленного релиза.
 * 2026-10-29 — секция websocket.
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

        foreach (['cache', 'queue', 'logging', 'retention', 'encryption', 'files', 'notifications', 'websocket', 'updates', 'providers'] as $section) {
            $file = $root . '/config/' . $section . '.php';
            if (is_file($file)) {
                $value = require $file;
                if (is_array($value)) {
                    $base[$section] = $value;
                }
            }
        }

        $release = $root . '/release.json';
        if (is_file($release)) {
            $manifest = json_decode((string)file_get_contents($release), true, 32, JSON_THROW_ON_ERROR);
            $base['version'] = (string)$manifest['version'];
        }
        $environment = realpath($root . '/.env');
        if ($environment !== false) {
            // Редактор .env пишет атомарным rename: ему нужен общий файл, иначе он заменит ссылку релиза.
            $base['environment_file'] = $environment;
        }
        return $base;
    }
}
