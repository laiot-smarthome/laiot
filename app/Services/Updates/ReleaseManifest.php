<?php

declare(strict_types=1);

namespace LaIOT\Services\Updates;

use Composer\Semver\VersionParser;
use RuntimeException;

/**
 * Неизменяемая идентичность релиза; канал не участвует в сравнении semver.
 * 2026-10-29 — одинаковый контракт сборки, загрузки и восстановления.
 */
final readonly class ReleaseManifest
{
    /** @param array<string, mixed> $data */
    public function __construct(public array $data)
    {
        foreach (['id', 'version', 'channel', 'commit', 'php'] as $key) {
            if (!is_string($data[$key] ?? null) || $data[$key] === '') {
                throw new RuntimeException('В релизе отсутствует ' . $key . '.');
            }
        }
        self::assertId($data['id']);
        if (!in_array($data['channel'], ['main', 'beta'], true)
            || $data['id'] !== $data['channel'] . '-' . $data['version']
            || preg_match('/^[a-f0-9]{40}$/D', $data['commit']) !== 1) {
            throw new RuntimeException('Некорректная идентичность релиза.');
        }
        (new VersionParser())->normalize($data['version']);
        (new VersionParser())->parseConstraints($data['php']);
        if (!is_array($data['extensions'] ?? null) || !is_array($data['modules'] ?? null)) {
            throw new RuntimeException('В релизе отсутствуют требования окружения или модули ядра.');
        }
    }

    public static function assertId(string $id): void
    {
        if (preg_match('/^(main|beta)-[0-9]+\.[0-9]+\.[0-9]+(?:-[a-zA-Z0-9.-]+)?(?:\+[a-zA-Z0-9.-]+)?$/D', $id) !== 1
            || strlen($id) > 120) {
            throw new RuntimeException('Неверный номер релиза.');
        }
    }

    public function id(): string
    {
        return (string)$this->data['id'];
    }
}
