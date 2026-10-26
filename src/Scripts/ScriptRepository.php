<?php

declare(strict_types=1);

namespace MajorDoMo\Scripts;

use InvalidArgumentException;
use MajorDoMo\Database\DatabaseManager;

/**
 * Пользовательские скрипты MyDoMo.
 */
final readonly class ScriptRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    /**
     * @return list<array{id:int,title:string,category_id:int,description:string,run_periodically:bool}>
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
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'title' => (string)$row->title,
                'category_id' => (int)$row->category_id,
                'description' => (string)($row->description ?? ''),
                'run_periodically' => (int)($row->run_periodically ?? 0) === 1,
            ])
            ->all();
    }

    /**
     * @return array{id:int,title:string,code:string,description:string,category_id:int,run_periodically:bool}|null
     */
    public function find(int $id): ?array
    {
        $row = $this->database->connection()->table('scripts')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : [
            'id' => (int)$row->id,
            'title' => (string)$row->title,
            'code' => (string)($row->code ?? ''),
            'description' => (string)($row->description ?? ''),
            'category_id' => (int)$row->category_id,
            'run_periodically' => (int)$row->run_periodically === 1,
        ];
    }

    /**
     * Поиск по названию: alpha `runScript()` принимал и ID, и заголовок.
     *
     * @return array{id:int,title:string,code:string,description:string,category_id:int,run_periodically:bool}|null
     */
    public function findByTitle(string $title): ?array
    {
        $row = $this->database->connection()->table('scripts')
            ->where('title', trim($title))
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : [
            'id' => (int)$row->id,
            'title' => (string)$row->title,
            'code' => (string)($row->code ?? ''),
            'description' => (string)($row->description ?? ''),
            'category_id' => (int)$row->category_id,
            'run_periodically' => (int)$row->run_periodically === 1,
        ];
    }

    public function create(string $title, string $code, string $description, int $categoryId, bool $runPeriodically): int
    {
        $now = date('Y-m-d H:i:s');

        return (int)$this->database->connection()->table('scripts')->insertGetId([
            'title' => $this->title($title),
            'code' => $code,
            'description' => $description,
            'category_id' => max(0, $categoryId),
            'run_periodically' => $runPeriodically ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function update(int $id, string $title, string $code, string $description, int $categoryId, bool $runPeriodically): void
    {
        $this->database->connection()->table('scripts')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update([
                'title' => $this->title($title),
                'code' => $code,
                'description' => $description,
                'category_id' => max(0, $categoryId),
                'run_periodically' => $runPeriodically ? 1 : 0,
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
