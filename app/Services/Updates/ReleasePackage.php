<?php

declare(strict_types=1);

namespace LaIOT\Services\Updates;

use RuntimeException;
use ZipArchive;

/**
 * Архив не может записывать var, .env, сторонние модули или выходить за каталог нового релиза.
 * 2026-10-29 — ограничение размера, путей, типов файлов и проверка вложенного манифеста.
 */
final class ReleasePackage
{
    public function extract(string $archive, string $directory, ReleaseManifest $manifest): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Архив релиза не открывается.');
        }
        try {
            $bytes = 0;
            $names = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (!is_array($stat)) {
                    throw new RuntimeException('Повреждён каталог архива.');
                }
                $name = $stat['name'];
                $top = explode('/', $name)[0];
                $zip->getExternalAttributesIndex($index, $operatingSystem, $attributes);
                $kind = ($attributes >> 16) & 0170000;
                if ($name === '' || str_contains($name, '\\') || str_contains($name, ':') || str_contains($name, "\0")
                    || str_starts_with($name, '/') || preg_match('~(^|/)\.{1,2}(/|$)~', $name) === 1
                    || isset($names[$name]) || !in_array($kind, [0, 0100000, 0040000], true)
                    || (!in_array($top, ReleaseLayout::CONTENT, true) && $name !== 'release.json' && !str_starts_with($name, 'modules/core/'))) {
                    throw new RuntimeException('Архив содержит запрещённый путь или тип файла.');
                }
                $names[$name] = true;
                $bytes += (int)$stat['size'];
                if ($bytes > 2 * 1024 * 1024 * 1024 || count($names) > 100000) {
                    throw new RuntimeException('Архив превышает допустимый размер поставки.');
                }
            }
            $free = disk_free_space(dirname($directory));
            if ($free === false || $free < $bytes + 256 * 1024 * 1024) {
                throw new RuntimeException('Недостаточно места для распаковки релиза.');
            }
            UpdateFiles::directory($directory);
            if (!$zip->extractTo($directory)) {
                throw new RuntimeException('Не удалось распаковать релиз.');
            }
            // Nginx обычно работает другим пользователем: UMask службы обновления не должен закрыть статику.
            chmod($directory, 0755);
            $directories = [$directory => true];
            foreach (array_keys($names) as $name) {
                $path = $directory . '/' . $name;
                chmod($path, is_dir($path) ? 0755 : 0644);
                if (is_file($path)) {
                    UpdateFiles::syncFile($path);
                } else {
                    $directories[$path] = true;
                }
                $parent = dirname($path);
                while ($parent !== $directory && str_starts_with($parent, $directory . '/')) {
                    chmod($parent, 0755);
                    $directories[$parent] = true;
                    $parent = dirname($parent);
                }
            }
            $paths = array_keys($directories);
            usort($paths, static fn (string $first, string $second): int => strlen($second) <=> strlen($first));
            foreach ($paths as $path) {
                UpdateFiles::syncDirectory($path);
            }
        } finally {
            $zip->close();
        }
        $embedded = new ReleaseManifest(UpdateFiles::read($directory . '/release.json'));
        foreach (['id', 'commit', 'php', 'extensions', 'modules'] as $key) {
            if ($embedded->data[$key] !== $manifest->data[$key]) {
                throw new RuntimeException('Содержимое архива не соответствует описанию релиза.');
            }
        }
        foreach (['bin/laiot', 'bootstrap/app.php', 'vendor/autoload.php', 'public/index.php', 'public/assets/admin/laiot.css'] as $required) {
            if (!is_file($directory . '/' . $required)) {
                throw new RuntimeException('В релизе отсутствует ' . $required);
            }
        }
    }
}
