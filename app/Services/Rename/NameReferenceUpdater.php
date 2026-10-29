<?php

declare(strict_types=1);

namespace LaIOT\Services\Rename;

use JsonException;
use LaIOT\Database\DatabaseManager;
use LaIOT\Foundation\Application;
use LaIOT\Services\Modules\ModuleManifest;
use LaIOT\Services\Runtime\PhpSyntaxValidator;
use Throwable;

/**
 * Каскадное переименование системных имён.
 *
 * 2026-10-27 — имена объектов, свойств и методов используются в сценариях,
 * методах, webhook, таймерах, настройках модулей и подписках. Переименование
 * одной строки в таблице ломало бы `sg()`, `callMethod()` и OnChange. Сервис
 * находит ссылки в данных и коде и обновляет их вместе с самой записью.
 *
 * Подписки модулей (`property_subscriptions`) хранят id, поэтому переживают
 * переименование без правки. Историю (`property_events`, `property_history`)
 * не переписываем: это снимки прошлых событий.
 */
final class NameReferenceUpdater
{
    /** @var array<string,string> path => new content */
    private array $pendingFiles = [];

    /** @var list<string> */
    private array $warnings = [];

    private int $changes = 0;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly Application $app,
        private readonly CodeReferenceRewriter $rewriter,
        private readonly PhpSyntaxValidator $syntax
    ) {
    }

    public function classRenamed(string $old, string $new): void
    {
        $this->reset();
        // Класс не участвует в sg()/callMethod(), поэтому точное имя класса
        // в коде неотличимо от объекта с тем же именем. Если такой объект
        // есть, не трогаем строки: важнее не сломать его вызовы.
        if ($this->titleExists('objects', $old)) {
            $this->warnings[] = 'Имя класса совпадает с именем объекта; ссылки в коде не переписаны.';
            return;
        }

        $literal = static fn (string $value): string => $value === $old ? $new : $value;
        $this->rewriteCodeTables(
            fn (string $code): array => $this->rewriter->className($code, $old, $new)
        );
        $this->rewriteSettings($literal);
        $this->collectModuleFiles(
            fn (string $code): array => $this->rewriter->className($code, $old, $new),
            $literal
        );
    }

    public function objectRenamed(int $objectId, string $old, string $new): void
    {
        $this->reset();
        $literal = static function (string $value) use ($old, $new): string {
            if ($value === $old) {
                return $new;
            }

            return str_replace($old . '.', $new . '.', $value);
        };

        $this->rewriteCodeTables(
            fn (string $code): array => $this->rewriter->object($code, $old, $new)
        );
        $this->rewriteSettings($literal);
        $this->rewriteObjectValues($old, $literal);
        $this->collectModuleFiles(
            fn (string $code): array => $this->rewriter->object($code, $old, $new),
            $literal
        );
    }

    public function propertyRenamed(
        int $propertyId,
        int $classId,
        int $objectId,
        string $old,
        string $new
    ): void {
        $this->reset();
        $objects = $this->scopeObjects($classId, $objectId);
        $methodIds = $this->methodScopeIds($classId, $objectId);
        $literal = $this->propertyLiteral($objects, $old, $new);

        $this->rewriteCodeTables(
            fn (string $code, bool $selfCalls): array => $this->rewriter->property(
                $code,
                $objects,
                $old,
                $new,
                $selfCalls
            ),
            $methodIds
        );
        $this->rewriteSettings($literal);
        $this->collectModuleFiles(
            fn (string $code): array => $this->rewriter->property($code, $objects, $old, $new, false),
            $literal
        );
    }

    public function methodRenamed(
        int $methodId,
        int $classId,
        int $objectId,
        string $old,
        string $new
    ): void {
        $this->reset();
        $objects = $this->scopeObjects($classId, $objectId);
        $methodIds = $this->methodScopeIds($classId, $objectId);
        $literal = $this->methodLiteral($objects, $old, $new);

        $this->rewriteCodeTables(
            fn (string $code, bool $selfCalls): array => $this->rewriter->method(
                $code,
                $objects,
                $old,
                $new,
                $selfCalls
            ),
            $methodIds
        );
        $this->rewriteOnChange($classId, $objectId, $objects, $old, $new);
        $this->rewriteSettings($literal);
        $this->collectModuleFiles(
            fn (string $code): array => $this->rewriter->method($code, $objects, $old, $new, false),
            $literal
        );
    }

    /**
     * Записать отложенные изменения файлов модулей после коммита БД.
     */
    public function flushModuleFiles(): void
    {
        $files = $this->pendingFiles;
        $this->pendingFiles = [];
        foreach ($files as $path => $content) {
            if (!is_writable($path)) {
                $this->warnings[] = 'Нет прав на запись: ' . $path;
                continue;
            }
            $temporary = tempnam(dirname($path), '.laiot-rename-');
            if ($temporary === false) {
                $this->warnings[] = 'Не удалось создать временный файл: ' . $path;
                continue;
            }
            $permissions = @fileperms($path);
            if (@file_put_contents($temporary, $content) === false) {
                @unlink($temporary);
                $this->warnings[] = 'Не удалось записать: ' . $path;
                continue;
            }
            if ($permissions !== false) {
                @chmod($temporary, $permissions & 0777);
            }
            if (!@rename($temporary, $path)) {
                @unlink($temporary);
                $this->warnings[] = 'Не удалось заменить файл: ' . $path;
                continue;
            }
            clearstatcache(true, $path);
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($path, true);
            }
            if (str_ends_with($path, DIRECTORY_SEPARATOR . 'module.json')) {
                $this->refreshManifestHash($path);
            }
        }
        if ($this->warnings !== []) {
            error_log('LaIOT rename: ' . implode('; ', $this->warnings));
        }
    }

    public function discardPendingFiles(): void
    {
        $this->pendingFiles = [];
    }

    /**
     * Манифест модуля изменился вместе с JSON — обновляем хеш в реестре,
     * иначе установщик увидит «изменённый манифест».
     */
    private function refreshManifestHash(string $path): void
    {
        if (!$this->has('module_registry', 'manifest_hash')) {
            return;
        }
        $directory = dirname($path);
        $type = str_contains($path, DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR)
            ? 'core'
            : 'module';
        try {
            $manifest = ModuleManifest::fromFile($path, $directory, $type);
            $hash = hash('sha256', (string)json_encode(
                $manifest->toArray(),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));
            $this->database->connection()->table('module_registry')
                ->where('module_id', $manifest->id())
                ->whereNull('deleted_at')
                ->update(['manifest_hash' => $hash, 'updated_at' => date('Y-m-d H:i:s')]);
        } catch (Throwable $error) {
            $this->warnings[] = 'Манифест модуля не обновлён: ' . $path . ' (' . $error->getMessage() . ')';
        }
    }

    public function changes(): int
    {
        return $this->changes;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    private function reset(): void
    {
        $this->pendingFiles = [];
        $this->warnings = [];
        $this->changes = 0;
    }

    /**
     * @param callable(string,bool):array{code:string,count:int} $rewrite
     * @param list<int>|null $methodScope
     */
    private function rewriteCodeTables(callable $rewrite, ?array $methodScope = null): void
    {
        $connection = $this->database->connection();
        $tables = [
            ['scripts', 'code', false],
            ['api_webhooks', 'code', false],
            ['runtime_timeouts', 'code', false],
            ['methods', 'code', true],
        ];
        foreach ($tables as [$table, $column, $isMethod]) {
            if (!$this->has($table, $column)) {
                continue;
            }
            foreach ($connection->table($table)->get(['id', $column]) as $row) {
                $code = (string)($row->{$column} ?? '');
                if ($code === '') {
                    continue;
                }
                $selfCalls = $isMethod
                    && $methodScope !== null
                    && in_array((int)$row->id, $methodScope, true);
                $result = $rewrite($code, $selfCalls);
                if ($result['count'] <= 0 || $result['code'] === $code) {
                    continue;
                }
                $connection->table($table)->where('id', (int)$row->id)->update([$column => $result['code']]);
                $this->changes += $result['count'];
            }
        }
    }

    /**
     * @param callable(string):string $transform
     */
    private function rewriteSettings(callable $transform): void
    {
        if ($this->has('module_registry', 'settings')) {
            $connection = $this->database->connection();
            foreach ($connection->table('module_registry')->get(['id', 'settings']) as $row) {
                $result = $this->rewriteJson((string)($row->settings ?? ''), $transform);
                if ($result === null || $result['count'] === 0) {
                    continue;
                }
                $connection->table('module_registry')->where('id', (int)$row->id)
                    ->update(['settings' => $result['json']]);
                $this->changes += $result['count'];
            }
        }

        if ($this->has('settings', 'value')) {
            $connection = $this->database->connection();
            foreach ($connection->table('settings')->get(['id', 'value']) as $row) {
                $value = (string)($row->value ?? '');
                if ($value === '') {
                    continue;
                }
                $json = $this->rewriteJson($value, $transform);
                if ($json !== null) {
                    if ($json['count'] === 0) {
                        continue;
                    }
                    $connection->table('settings')->where('id', (int)$row->id)->update(['value' => $json['json']]);
                    $this->changes += $json['count'];
                    continue;
                }
                $new = $transform($value);
                if ($new === $value) {
                    continue;
                }
                $connection->table('settings')->where('id', (int)$row->id)->update(['value' => $new]);
                $this->changes++;
            }
        }
    }

    /**
     * Значения свойств — данные, а не код. Меняем только точное имя объекта:
     * так обновляются ссылки вида «связанный объект», не трогая состояния.
     *
     * @param callable(string):string $transform
     */
    private function rewriteObjectValues(string $old, callable $transform): void
    {
        if (!$this->has('property_values', 'value')) {
            return;
        }
        $connection = $this->database->connection();
        foreach ($connection->table('property_values')
            ->where('object_id', '>', 0)
            ->whereNotNull('value')
            ->where('value', 'like', '%' . $old . '%')
            ->get(['id', 'value']) as $row) {
            $value = (string)$row->value;
            $new = $transform($value);
            if ($new === $value) {
                continue;
            }
            $connection->table('property_values')->where('id', (int)$row->id)
                ->update(['value' => $new, 'updated_at' => date('Y-m-d H:i:s')]);
            $this->changes++;
        }
    }

    /**
     * OnChange-метод хранится в свойстве по имени, а не по id.
     *
     * @param list<string> $objects
     */
    private function rewriteOnChange(int $classId, int $objectId, array $objects, string $old, string $new): void
    {
        if (!$this->has('properties', 'on_change')) {
            return;
        }
        $connection = $this->database->connection();
        $classIds = $objectId > 0
            ? $this->ancestorClassIds($classId)
            : $this->subtreeClassIds($classId);
        if ($classIds === []) {
            return;
        }
        $query = $connection->table('properties')
            ->whereIn('class_id', $classIds)
            ->where('on_change', '<>', '');
        if ($objectId > 0) {
            $query->where('object_id', $objectId);
        }
        foreach ($query->get(['id', 'on_change']) as $row) {
            $value = (string)$row->on_change;
            if ($value === $old) {
                $value = $new;
            } else {
                foreach ($objects as $object) {
                    $value = str_replace($object . '.' . $old, $object . '.' . $new, $value);
                }
            }
            if ($value === (string)$row->on_change) {
                continue;
            }
            $connection->table('properties')->where('id', (int)$row->id)
                ->update(['on_change' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
            $this->changes++;
        }
    }

    /**
     * @param callable(string):array{code:string,count:int} $rewriteCode
     * @param callable(string):string $transform
     */
    private function collectModuleFiles(callable $rewriteCode, callable $transform): void
    {
        $root = $this->app->root() . DIRECTORY_SEPARATOR . 'modules';
        if (!is_dir($root)) {
            return;
        }
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }
                $path = $file->getPathname();
                if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
                    || str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
                    continue;
                }
                $extension = strtolower($file->getExtension());
                if ($extension === 'php' && !str_ends_with($path, '.blade.php')) {
                    $code = @file_get_contents($path);
                    if ($code === false || $code === '') {
                        continue;
                    }
                    $result = $rewriteCode($code);
                    if ($result['count'] <= 0 || $result['code'] === $code) {
                        continue;
                    }
                    if (!$this->validPhp($result['code'])) {
                        $this->warnings[] = 'Пропущен PHP-файл с невалидным кодом: ' . $path;
                        continue;
                    }
                    $this->pendingFiles[$path] = $result['code'];
                    $this->changes += $result['count'];
                } elseif ($extension === 'json') {
                    $json = @file_get_contents($path);
                    if ($json === false || $json === '') {
                        continue;
                    }
                    $result = $this->rewriteJson($json, $transform);
                    if ($result === null || $result['count'] === 0) {
                        continue;
                    }
                    $this->pendingFiles[$path] = $result['json'];
                    $this->changes += $result['count'];
                }
            }
        } catch (Throwable $error) {
            $this->warnings[] = 'Обход модулей не выполнен: ' . $error->getMessage();
        }
    }

    /**
     * @param callable(string):string $transform
     * @return array{json:string,count:int}|null
     */
    private function rewriteJson(string $json, callable $transform): ?array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }
        $count = 0;
        $walk = function (mixed &$value) use (&$walk, $transform, &$count): void {
            if (is_array($value)) {
                $copy = [];
                foreach ($value as $key => $child) {
                    $newKey = is_string($key) ? $transform($key) : $key;
                    if (is_string($key) && $newKey !== $key) {
                        $count++;
                    }
                    $walk($child);
                    $copy[$newKey] = $child;
                }
                $value = $copy;

                return;
            }
            if (!is_string($value)) {
                return;
            }
            $new = $transform($value);
            if ($new !== $value) {
                $count++;
                $value = $new;
            }
        };
        $walk($decoded);
        if ($count === 0) {
            return ['json' => $json, 'count' => 0];
        }
        try {
            $encoded = json_encode($decoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return null;
        }

        return ['json' => $encoded, 'count' => $count];
    }

    /**
     * @param list<string> $objects
     * @return callable(string):string
     */
    private function propertyLiteral(array $objects, string $old, string $new): callable
    {
        $exact = $this->titleUnique('properties', $old);

        return static function (string $value) use ($objects, $old, $new, $exact): string {
            if ($exact && $value === $old) {
                return $new;
            }
            foreach ($objects as $object) {
                $value = str_replace($object . '.' . $old, $object . '.' . $new, $value);
            }

            return $value;
        };
    }

    /**
     * @param list<string> $objects
     * @return callable(string):string
     */
    private function methodLiteral(array $objects, string $old, string $new): callable
    {
        $exact = $this->titleUnique('methods', $old);

        return static function (string $value) use ($objects, $old, $new, $exact): string {
            if ($exact && $value === $old) {
                return $new;
            }
            foreach ($objects as $object) {
                $value = str_replace($object . '.' . $old, $object . '.' . $new, $value);
            }

            return $value;
        };
    }

    /**
     * @return list<string>
     */
    private function scopeObjects(int $classId, int $objectId): array
    {
        if (!$this->has('objects', 'title')) {
            return [];
        }
        $connection = $this->database->connection();
        if ($objectId > 0) {
            $title = $connection->table('objects')->where('id', $objectId)->value('title');

            return $title === null ? [] : [(string)$title];
        }
        $classIds = $this->subtreeClassIds($classId);
        if ($classIds === []) {
            return [];
        }

        return $connection->table('objects')
            ->whereIn('class_id', $classIds)
            ->whereNull('deleted_at')
            ->pluck('title')
            ->map(static fn (mixed $title): string => (string)$title)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function methodScopeIds(int $classId, int $objectId): array
    {
        if (!$this->has('methods', 'code')) {
            return [];
        }
        $connection = $this->database->connection();
        if ($objectId > 0) {
            $classIds = $this->ancestorClassIds($classId);

            return $connection->table('methods')
                ->where('object_id', $objectId)
                ->orWhere(static function ($query) use ($classIds): void {
                    $query->whereIn('class_id', $classIds)->where('object_id', 0);
                })
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int)$id)
                ->all();
        }
        $classIds = $this->subtreeClassIds($classId);
        if ($classIds === []) {
            return [];
        }

        return $connection->table('methods')
            ->whereIn('class_id', $classIds)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int)$id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function subtreeClassIds(int $classId): array
    {
        if ($classId <= 0 || !$this->has('classes', 'parent_id')) {
            return [];
        }
        $rows = $this->database->connection()->table('classes')
            ->whereNull('deleted_at')
            ->get(['id', 'parent_id']);
        $children = [];
        foreach ($rows as $row) {
            $children[(int)$row->parent_id][] = (int)$row->id;
        }
        $ids = [];
        $queue = [$classId];
        while ($queue !== []) {
            $current = (int)array_shift($queue);
            if (in_array($current, $ids, true)) {
                continue;
            }
            $ids[] = $current;
            foreach ($children[$current] ?? [] as $child) {
                $queue[] = $child;
            }
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function ancestorClassIds(int $classId): array
    {
        if ($classId <= 0 || !$this->has('classes', 'parent_id')) {
            return [];
        }
        $rows = $this->database->connection()->table('classes')
            ->whereNull('deleted_at')
            ->get(['id', 'parent_id']);
        $parents = [];
        foreach ($rows as $row) {
            $parents[(int)$row->id] = (int)$row->parent_id;
        }
        $ids = [];
        $current = $classId;
        while ($current > 0 && !in_array($current, $ids, true)) {
            $ids[] = $current;
            $current = $parents[$current] ?? 0;
        }

        return $ids;
    }

    private function titleUnique(string $table, string $title): bool
    {
        if (!$this->has($table, 'title')) {
            return false;
        }
        $query = $this->database->connection()->table($table)
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)]);
        if ($this->has($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return (int)$query->count() <= 1;
    }

    private function titleExists(string $table, string $title): bool
    {
        if (!$this->has($table, 'title')) {
            return false;
        }
        $query = $this->database->connection()->table($table)
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)]);
        if ($this->has($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->exists();
    }

    private function validPhp(string $code): bool
    {
        if (preg_match('/^\s*<\?php\s*/', $code) === 1) {
            $code = (string)preg_replace('/^\s*<\?php\s*/', '', $code, 1);
        }

        return $this->syntax->validate($code) === null;
    }

    private function has(string $table, string $column = ''): bool
    {
        try {
            $schema = $this->database->connection()->getSchemaBuilder();
            if (!$schema->hasTable($table)) {
                return false;
            }

            return $column === '' || $schema->hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }
}
