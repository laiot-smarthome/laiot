<?php

declare(strict_types=1);

namespace LaIOT\Services\Updates;

use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Читает только опубликованные номерные выпуски выбранного репозитория GitHub.
 * 2026-10-30 — все страницы каталога и явная перепроверка HTTP-кэша GitHub.
 * 2026-10-29 — готовый архив, метаданные, описание и проверка SHA-256 перед распаковкой.
 */
final readonly class ReleaseCatalog
{
    public string $repository;

    public function __construct(private HttpClientInterface $http, string $repository)
    {
        $repository = preg_replace('~^https://github\.com/~', '', rtrim(trim($repository), '/')) ?? '';
        $repository = preg_replace('/\.git$/', '', $repository) ?? '';
        if (preg_match('~^[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+$~D', $repository) !== 1) {
            throw new RuntimeException('Неверный репозиторий обновлений.');
        }
        $this->repository = $repository;
    }

    /** @return list<array<string, mixed>> */
    public function releases(): array
    {
        $result = [];
        for ($page = 1; ; $page++) {
            $releases = $this->http->request('GET', 'https://api.github.com/repos/' . $this->repository . '/releases?per_page=100&page=' . $page, [
                'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'LaIOT-Updater', 'Cache-Control' => 'no-cache, max-age=0', 'Pragma' => 'no-cache'],
                'timeout' => 15, 'max_duration' => 30,
            ])->toArray();
            foreach ($releases as $release) {
                if (!is_array($release) || ($release['draft'] ?? true) === true) {
                    continue;
                }
                $id = (string)($release['tag_name'] ?? '');
                if (preg_match('/^(main|beta)-[0-9]+\.[0-9]+\.[0-9]+/', $id) !== 1) {
                    continue;
                }
                ReleaseManifest::assertId($id);
                $assets = [];
                foreach ((array)($release['assets'] ?? []) as $asset) {
                    $assets[(string)$asset['name']] = $asset;
                }
                if (!isset($assets['laiot-release.json'], $assets['laiot.zip'])) {
                    continue;
                }
                $result[] = [
                    'id' => $id, 'channel' => str_starts_with($id, 'main-') ? 'main' : 'beta',
                    'title' => (string)($release['name'] ?: $id), 'notes' => (string)($release['body'] ?? ''),
                    'published_at' => (string)($release['published_at'] ?? ''),
                    'manifest_url' => $this->assetUrl((string)$assets['laiot-release.json']['browser_download_url'], $id),
                    'archive_url' => $this->assetUrl((string)$assets['laiot.zip']['browser_download_url'], $id),
                    'size' => (int)($assets['laiot.zip']['size'] ?? 0),
                ];
            }
            if (count($releases) < 100) {
                break;
            }
        }
        return $result;
    }

    /** @param array<string, mixed> $release */
    public function manifest(array $release): ReleaseManifest
    {
        $response = $this->http->request('GET', $this->assetUrl((string)$release['manifest_url'], (string)$release['id']), [
            'timeout' => 15, 'max_duration' => 30,
        ]);
        $data = $response->toArray();
        if (($data['id'] ?? '') !== $release['id'] || !is_string($data['sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $data['sha256']) !== 1 || !is_int($data['size'] ?? null)
            || $data['size'] < 1) {
            throw new RuntimeException('Метаданные архива релиза повреждены.');
        }
        return new ReleaseManifest($data);
    }

    /** @param array<string, mixed> $release */
    public function download(array $release, ReleaseManifest $manifest, string $path): void
    {
        $response = $this->http->request('GET', $this->assetUrl((string)$release['archive_url'], $manifest->id()), [
            'timeout' => 30, 'max_duration' => 1800,
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('GitHub не отдал архив релиза.');
        }
        $stream = fopen($path, 'xb');
        if ($stream === false) {
            throw new RuntimeException('Не удалось сохранить архив релиза.');
        }
        chmod($path, 0600);
        $size = 0;
        try {
            foreach ($this->http->stream($response) as $chunk) {
                $contents = $chunk->getContent();
                $size += strlen($contents);
                if ($size > (int)$manifest->data['size'] || fwrite($stream, $contents) !== strlen($contents)) {
                    throw new RuntimeException('Размер архива не совпадает с релизом или закончился диск.');
                }
            }
            fflush($stream);
            fsync($stream);
        } finally {
            fclose($stream);
        }
        if ($size !== $manifest->data['size'] || !hash_equals((string)$manifest->data['sha256'], (string)hash_file('sha256', $path))) {
            throw new RuntimeException('Контрольная сумма архива релиза не совпала.');
        }
    }

    private function assetUrl(string $url, string $id): string
    {
        $prefix = 'https://github.com/' . $this->repository . '/releases/download/' . rawurlencode($id) . '/';
        if (!str_starts_with($url, $prefix) || !in_array(substr($url, strlen($prefix)), ['laiot-release.json', 'laiot.zip'], true)) {
            throw new RuntimeException('Архив должен принадлежать выбранному релизу GitHub.');
        }
        return $url;
    }
}
