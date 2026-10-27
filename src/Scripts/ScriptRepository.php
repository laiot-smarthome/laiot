<?php

declare(strict_types=1);

namespace MajorDoMo\Scripts;

use InvalidArgumentException;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Runtime\InvalidUserCode;
use MajorDoMo\Runtime\PhpSyntaxValidator;

/**
 * Пользовательские скрипты MyDoMo.
 *
 * 2026-10-27 — вместо признака расписания хранится системное событие запуска;
 *              код проверяется синтаксически до сохранения.
 */
final readonly class ScriptRepository
{
    public function __construct(
        private DatabaseManager $database,
        private PhpSyntaxValidator $syntax
    ) {
    }

    /**
     * @return list<array{id:int,title:string,category_id:int,description:string,event_name:string,use_core:bool}>
     */
    public function all(string $search = '', int $categoryId = 0): array
    {
        $query = $this->database->connection()->table('scripts')->whereNull('deleted_at');
        if ($categoryId !== 0) {
            $query->where('category_id', $categoryId);
        }
        if ($search !== '') {
            $query->where(static function ($query) use ($search): void {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        return $query->orderBy('title')
            ->get()
            ->map(fn (object $row): array => $this->row($row))
            ->all();
    }

    /**
     * @return array{id:int,title:string,code:string,description:string,category_id:int,event_name:string,use_core:bool}|null
     */
    public function find(int $id): ?array
    {
        $row = $this->database->connection()->table('scripts')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : $this->row($row) + ['code' => (string)($row->code ?? '')];
    }

    /**
     * Поиск по названию: alpha `runScript()` принимал и ID, и заголовок.
     *
     * @return array{id:int,title:string,code:string,description:string,category_id:int,event_name:string,use_core:bool}|null
     */
    public function findByTitle(string $title): ?array
    {
        $row = $this->database->connection()->table('scripts')
            ->where('title', trim($title))
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : $this->row($row) + ['code' => (string)($row->code ?? '')];
    }

    /**
     * Сценарии, привязанные к системному событию.
     *
     * @return list<array{id:int,title:string}>
     */
    public function byEvent(string $event): array
    {
        $event = trim($event);
        if ($event === '') {
            return [];
        }

        return $this->database->connection()->table('scripts')
            ->where('event_name', $event)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'title'])
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'title' => (string)$row->title,
            ])
            ->all();
    }

    public function create(
        string $title,
        string $code,
        string $description,
        int $categoryId,
        string $eventName = '',
        bool $useCore = true
    ): int {
        $title = $this->title($title);
        $eventName = ScriptTriggers::normalize($eventName);
        $this->assertCode($code);
        $now = date('Y-m-d H:i:s');

        return (int)$this->database->connection()->table('scripts')->insertGetId([
            'title' => $title,
            'code' => $code,
            'description' => $description,
            'category_id' => max(0, $categoryId),
            'event_name' => $eventName,
            'use_core' => $useCore,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function update(
        int $id,
        string $title,
        string $code,
        string $description,
        int $categoryId,
        string $eventName = '',
        bool $useCore = true
    ): void {
        $title = $this->title($title);
        $eventName = ScriptTriggers::normalize($eventName);
        $this->assertCode($code);
        $this->database->connection()->table('scripts')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update([
                'title' => $title,
                'code' => $code,
                'description' => $description,
                'category_id' => max(0, $categoryId),
                'event_name' => $eventName,
                'use_core' => $useCore,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function delete(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('scripts')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    /**
     * @return list<array{id:int,title:string}>
     */
    public function categories(): array
    {
        return $this->database->connection()->table('script_categories')
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get()
            ->map(static fn (object $row): array => ['id' => (int)$row->id, 'title' => (string)$row->title])
            ->all();
    }

    /**
     * Количество сценариев в каждой категории.
     *
     * @return array<int,int>
     */
    public function categoryCounts(): array
    {
        $counts = [];
        $rows = $this->database->connection()->table('scripts')
            ->whereNull('deleted_at')
            ->select('category_id')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('category_id')
            ->get();
        foreach ($rows as $row) {
            $counts[(int)$row->category_id] = (int)$row->aggregate;
        }

        return $counts;
    }

    public function createCategory(string $title): int
    {
        $now = date('Y-m-d H:i:s');

        return (int)$this->database->connection()->table('script_categories')->insertGetId([
            'title' => $this->categoryTitle($title),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function renameCategory(int $id, string $title): void
    {
        $this->database->connection()->table('script_categories')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['title' => $this->categoryTitle($title), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Мягкое удаление категории: сценарии остаются, но теряют категорию.
     */
    public function deleteCategory(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $connection = $this->database->connection();
        $connection->transaction(static function () use ($connection, $id, $now): void {
            $connection->table('scripts')
                ->where('category_id', $id)
                ->whereNull('deleted_at')
                ->update(['category_id' => 0, 'updated_at' => $now]);
            $connection->table('script_categories')
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now, 'updated_at' => $now]);
        });
    }

    /**
     * Единственная точка сохранения кода: invalid PHP не попадает в базу.
     */
    private function assertCode(string $code): void
    {
        $error = $this->syntax->validate($code);
        if ($error === null) {
            return;
        }

        throw new InvalidUserCode($error['line'], $error['message']);
    }

    /**
     * @return array{id:int,title:string,category_id:int,description:string,event_name:string,use_core:bool}
     */
    private function row(object $row): array
    {
        return [
            'id' => (int)$row->id,
            'title' => (string)$row->title,
            'category_id' => (int)$row->category_id,
            'description' => (string)($row->description ?? ''),
            'event_name' => (string)($row->event_name ?? ''),
            // Старые установки без миграции считаем «с ядром».
            'use_core' => (int)($row->use_core ?? 1) === 1,
        ];
    }

    private function categoryTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 191) {
            throw new InvalidArgumentException('Название категории обязательно, до 191 символа.');
        }

        return $title;
    }

    private function title(string $title): string
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 191) {
            throw new InvalidArgumentException('Название скрипта обязательно, до 191 символа.');
        }

        return $title;
    }
}
