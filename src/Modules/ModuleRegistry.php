<?php

declare(strict_types=1);

namespace MajorDoMo\Modules;

use JsonException;
use MajorDoMo\Database\DatabaseManager;

/**
 * Реестр сторонних модулей и применённых миграций.
 *
 * 2026-10-27 — чтение и сохранение `settings` модуля в колонке реестра.
 */
final readonly class ModuleRegistry
{
    public function __construct(private DatabaseManager $database)
    {
    }

    public function available(): bool
    {
        $schema = $this->database->connection()->getSchemaBuilder();

        return $schema->hasTable('module_registry') && $schema->hasTable('module_migrations');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->database->connection()->table('module_registry')
            ->whereNull('deleted_at')
            ->orderBy('module_id')
            ->get()
            ->map(static fn (object $row): array => get_object_vars($row))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        $row = $this->database->connection()->table('module_registry')
            ->where('module_id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : get_object_vars($row);
    }

    public function isInstalled(string $id): bool
    {
        return $this->find($id) !== null;
    }

    /**
     * Сохранённые значения настроек модуля.
     *
     * Битый JSON читается как пустой набор: модуль продолжает работать на
     * умолчаниях схемы, а не падает на старой записи реестра.
     *
     * @return array<string, mixed>
     */
    public function settings(string $id): array
    {
        $raw = $this->find($id)['settings'] ?? null;
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $values
     */
    public function saveSettings(string $id, array $values): void
    {
        $this->database->connection()->table('module_registry')
            ->where('module_id', $id)
            ->whereNull('deleted_at')
            ->update([
                'settings' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function isEnabled(string $id): bool
    {
        $row = $this->find($id);

        return $row !== null && (int)($row['enabled'] ?? 0) === 1;
    }

    public function install(ModuleManifest $manifest, bool $enabled = true): void
    {
        $now = date('Y-m-d H:i:s');
        $data = [
            'module_id' => $manifest->id(),
            'type' => $manifest->type,
            'name' => (string)$manifest->data['name'],
            'version' => (string)$manifest->data['version'],
            'path' => $manifest->path,
            'enabled' => $enabled ? 1 : 0,
            'manifest_hash' => hash('sha256', (string)json_encode($manifest->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'updated_at' => $now,
            'deleted_at' => null,
        ];

        $existing = $this->database->connection()->table('module_registry')
            ->where('module_id', $manifest->id())
            ->first();
        if ($existing === null) {
            $this->database->connection()->table('module_registry')->insert($data + [
                'installed_at' => $now,
                'created_at' => $now,
                'settings' => null,
            ]);

            return;
        }

        $this->database->connection()->table('module_registry')
            ->where('module_id', $manifest->id())
            ->update($data + ['installed_at' => $now]);
    }

    public function setEnabled(string $id, bool $enabled): void
    {
        $this->database->connection()->table('module_registry')
            ->where('module_id', $id)
            ->whereNull('deleted_at')
            ->update([
                'enabled' => $enabled ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function remove(string $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('module_registry')
            ->where('module_id', $id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    /**
     * @return list<string>
     */
    public function appliedMigrations(string $moduleId): array
    {
        return $this->database->connection()->table('module_migrations')
            ->where('module_id', $moduleId)
            ->orderBy('migration')
            ->pluck('migration')
            ->map(static fn (mixed $value): string => (string)$value)
            ->all();
    }

    public function markMigration(string $moduleId, string $migration): void
    {
        $this->database->connection()->table('module_migrations')->insertOrIgnore([
            'module_id' => $moduleId,
            'migration' => $migration,
            'applied_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function forgetMigrations(string $moduleId): void
    {
        $this->database->connection()->table('module_migrations')->where('module_id', $moduleId)->delete();
    }
}
