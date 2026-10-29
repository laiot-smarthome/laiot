<?php

declare(strict_types=1);

namespace LaIOT\Services\Search;

use Illuminate\Database\Query\Builder;
use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Modules\ModuleManager;
use LaIOT\Services\Panel\CoreSections;

/**
 * Поиск по сущностям нового ядра для шапки админки.
 *
 * Один запрос пользователя ищет объекты, классы, свойства, методы, скрипты,
 * пользователей, локации и core-модули. Поиск идёт по новым lowercase-таблицам;
 * старые alpha-таблицы и `$db` не используются. Совпадение — все слова запроса
 * встречаются в названии/описании/метаданных; точное и префиксное совпадение
 * названия получает больший вес.
 *
 * 2026-10-26 — сервис создан для alpha-shell parity: поиск в шапке и drawer.
 * 2026-10-27 — релевантность считается до ограничения числа результатов.
 */
final readonly class PanelSearch
{
    public const MIN_QUERY = 3;
    public const DEFAULT_LIMIT = 10;
    public const MAX_LIMIT = 50;
    private const MAX_QUERY = 100;
    private const MAX_TOKENS = 8;

    public function __construct(
        private DatabaseManager $database,
        private ModuleManager $modules
    ) {
    }

    /**
     * @return array{
     *     ok:bool,
     *     query:string,
     *     total:int,
     *     counts:array<string,int>,
     *     sections:array<string,list<array{type:string,title:string,url:string,description:string,meta:list<string>,action:string}>>,
     *     message:string
     * }
     */
    public function search(string $query, int $limit = self::DEFAULT_LIMIT): array
    {
        $query = mb_substr(trim($query), 0, self::MAX_QUERY);
        $normalized = mb_strtolower($query);
        if (mb_strlen($normalized) < self::MIN_QUERY) {
            return $this->emptyResult($query, 'Введите не менее ' . self::MIN_QUERY . ' символов.');
        }

        $tokens = $this->tokens($normalized);
        if ($tokens === []) {
            return $this->emptyResult($query, 'Введите не менее ' . self::MIN_QUERY . ' символов.');
        }

        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $sections = [];
        $counts = [];

        $this->addSection($sections, $counts, 'Объекты', $this->objects($tokens, $normalized, $limit), $limit);
        $this->addSection($sections, $counts, 'Классы', $this->classes($tokens, $normalized, $limit), $limit);
        $this->addSection($sections, $counts, 'Свойства', $this->properties($tokens, $normalized, $limit), $limit);
        $this->addSection($sections, $counts, 'Методы', $this->methods($tokens, $normalized, $limit), $limit);
        $this->addSection($sections, $counts, 'Скрипты', $this->scripts($tokens, $normalized, $limit), $limit);
        $this->addSection($sections, $counts, 'Пользователи', $this->users($tokens, $normalized, $limit), $limit);
        $this->addSection($sections, $counts, 'Локации', $this->locations($tokens, $normalized, $limit), $limit);
        $this->addSection($sections, $counts, 'Разделы', $this->coreSections($tokens, $normalized, $limit), $limit);
        $this->addSection($sections, $counts, 'Модули', $this->modules($tokens, $normalized, $limit), $limit);

        $total = 0;
        foreach ($sections as $items) {
            $total += count($items);
        }

        return [
            'ok' => true,
            'query' => $query,
            'total' => $total,
            'counts' => $counts,
            'sections' => $sections,
            'message' => $total > 0 ? '' : 'Ничего не найдено',
        ];
    }

    /**
     * @param list<string> $tokens
     * @return list<array{type:string,title:string,url:string,description:string,meta:list<string>,action:string,_score:int}>
     */
    private function objects(array $tokens, string $query, int $limit): array
    {
        $rows = $this->likeQuery('objects as o', ['o.title', 'o.description', 'c.title'], $tokens)
            ->leftJoin('classes as c', 'c.id', '=', 'o.class_id')
            ->whereNull('o.deleted_at')
            ->orderBy('o.title')
            ->select(['o.id', 'o.title', 'o.description', 'c.title as class_title'])->cursor();

        return $this->collect($rows, $tokens, $query, static function (object $row): array {
            $title = (string)$row->title;

            return [
                'type' => 'object',
                'title' => $title,
                'url' => '/objects/' . (int)$row->id . '/edit',
                'description' => (string)($row->description ?? ''),
                'meta' => [(string)($row->class_title ?? '')],
                'action' => '',
            ];
        });
    }

    /**
     * @param list<string> $tokens
     * @return list<array{type:string,title:string,url:string,description:string,meta:list<string>,action:string,_score:int}>
     */
    private function classes(array $tokens, string $query, int $limit): array
    {
        $rows = $this->likeQuery('classes', ['title'], $tokens)
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->select(['id', 'title'])->cursor();

        return $this->collect($rows, $tokens, $query, static fn (object $row): array => [
            'type' => 'class',
            'title' => (string)$row->title,
            'url' => '/classes/' . (int)$row->id . '/edit',
            'description' => '',
            'meta' => ['Класс'],
            'action' => '',
        ]);
    }

    /**
     * @param list<string> $tokens
     * @return list<array{type:string,title:string,url:string,description:string,meta:list<string>,action:string,_score:int}>
     */
    private function properties(array $tokens, string $query, int $limit): array
    {
        $rows = $this->likeQuery('properties as p', ['p.title', 'p.description', 'c.title', 'o.title'], $tokens)
            ->leftJoin('classes as c', 'c.id', '=', 'p.class_id')
            ->leftJoin('objects as o', 'o.id', '=', 'p.object_id')
            ->whereNull('p.deleted_at')
            ->orderBy('p.title')
            ->select([
                'p.id',
                'p.class_id',
                'p.object_id',
                'p.title',
                'p.description',
                'c.title as class_title',
                'o.title as object_title',
            ])->cursor();

        return $this->collect($rows, $tokens, $query, static function (object $row): array {
            $objectId = (int)($row->object_id ?? 0);
            $classId = (int)($row->class_id ?? 0);
            $url = $objectId > 0
                ? '/objects/' . $objectId . '/edit?tab=properties'
                : ($classId > 0 ? '/classes/' . $classId . '/edit?tab=properties' : '/classes');
            $meta = [(string)($row->class_title ?? '')];
            if ($objectId > 0) {
                $meta[] = 'объект: ' . (string)($row->object_title ?? '');
            }

            return [
                'type' => 'property',
                'title' => (string)$row->title,
                'url' => $url,
                'description' => (string)($row->description ?? ''),
                'meta' => $meta,
                'action' => '',
            ];
        });
    }

    /**
     * @param list<string> $tokens
     * @return list<array{type:string,title:string,url:string,description:string,meta:list<string>,action:string,_score:int}>
     */
    private function methods(array $tokens, string $query, int $limit): array
    {
        $rows = $this->likeQuery('methods as m', ['m.title', 'm.description', 'c.title', 'o.title'], $tokens)
            ->leftJoin('classes as c', 'c.id', '=', 'm.class_id')
            ->leftJoin('objects as o', 'o.id', '=', 'm.object_id')
            ->whereNull('m.deleted_at')
            ->orderBy('m.title')
            ->select([
                'm.id',
                'm.class_id',
                'm.object_id',
                'm.title',
                'm.description',
                'c.title as class_title',
                'o.title as object_title',
            ])->cursor();

        return $this->collect($rows, $tokens, $query, static function (object $row): array {
            $objectId = (int)($row->object_id ?? 0);
            $classId = (int)($row->class_id ?? 0);
            $url = $objectId > 0
                ? '/objects/' . $objectId . '/edit?tab=methods'
                : ($classId > 0 ? '/classes/' . $classId . '/edit?tab=methods' : '/classes');
            $meta = [(string)($row->class_title ?? '')];
            if ($objectId > 0) {
                $meta[] = 'объект: ' . (string)($row->object_title ?? '');
            }

            return [
                'type' => 'method',
                'title' => (string)$row->title,
                'url' => $url,
                'description' => (string)($row->description ?? ''),
                'meta' => $meta,
                'action' => '',
            ];
        });
    }

    /**
     * @param list<string> $tokens
     * @return list<array{type:string,title:string,url:string,description:string,meta:list<string>,action:string,_score:int}>
     */
    private function scripts(array $tokens, string $query, int $limit): array
    {
        $rows = $this->likeQuery('scripts', ['title', 'description'], $tokens)
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->select(['id', 'title', 'description'])->cursor();

        return $this->collect($rows, $tokens, $query, static fn (object $row): array => [
            'type' => 'script',
            'title' => (string)$row->title,
            'url' => '/scripts/' . (int)$row->id . '/edit',
            'description' => (string)($row->description ?? ''),
            'meta' => ['Скрипт'],
            'action' => '',
        ]);
    }

    /**
     * @param list<string> $tokens
     * @return list<array{type:string,title:string,url:string,description:string,meta:list<string>,action:string,_score:int}>
     */
    private function users(array $tokens, string $query, int $limit): array
    {
        $rows = $this->likeQuery('users', ['username', 'name'], $tokens)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->select(['id', 'username', 'name', 'is_admin'])->cursor();

        return $this->collect($rows, $tokens, $query, static function (object $row): array {
            $username = (string)$row->username;
            $name = trim((string)$row->name);

            return [
                'type' => 'user',
                'title' => $name !== '' ? $name : $username,
                'url' => '/users/' . (int)$row->id . '/edit',
                'description' => $name !== '' ? $username : '',
                'meta' => [(int)($row->is_admin ?? 0) === 1 ? 'Администратор' : 'Пользователь'],
                'action' => '',
            ];
        });
    }

    /**
     * @param list<string> $tokens
     * @return list<array{type:string,title:string,url:string,description:string,meta:list<string>,action:string,_score:int}>
     */
    private function locations(array $tokens, string $query, int $limit): array
    {
        $rows = $this->likeQuery('locations', ['title'], $tokens)
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->select(['id', 'title'])->cursor();

        return $this->collect($rows, $tokens, $query, static fn (object $row): array => [
            'type' => 'location',
            'title' => (string)$row->title,
            'url' => '/locations/' . (int)$row->id . '/edit',
            'description' => '',
            'meta' => ['Локация'],
            'action' => '',
        ]);
    }

    /**
     * @param list<string> $tokens
     * @return list<array{type:string,title:string,url:string,description:string,meta:list<string>,action:string,_score:int}>
     */
    private function coreSections(array $tokens, string $query, int $limit): array
    {
        $items = [];
        foreach (CoreSections::all() as $section) {
            $haystack = mb_strtolower($section['label'] . ' ' . $section['id']);
            foreach ($tokens as $token) {
                if (!str_contains($haystack, $token)) {
                    continue 2;
                }
            }
            $items[] = [
                'type' => 'section',
                'title' => $section['label'],
                'url' => $section['href'],
                'description' => CoreSections::groupLabel($section['group']),
                'meta' => ['ядро'],
                'action' => '',
                '_score' => $this->score($section['label'], $section['id'], [], $query, $tokens) + 20,
            ];
        }

        return $items;
    }

    /**
     * @param list<string> $tokens
     * @return list<array{type:string,title:string,url:string,description:string,meta:list<string>,action:string,_score:int}>
     */
    private function modules(array $tokens, string $query, int $limit): array
    {
        $items = [];
        foreach ($this->modules->all() as $id => $module) {
            $name = (string)($module['name'] ?? $id);
            $haystack = mb_strtolower($id . ' ' . $name);
            foreach ($tokens as $token) {
                if (!str_contains($haystack, $token)) {
                    continue 2;
                }
            }

            $items[] = [
                'type' => 'module',
                'title' => $name,
                'url' => '/module/' . $id,
                'description' => $id,
                'meta' => ['core', 'v' . (string)($module['version'] ?? '')],
                'action' => '',
                '_score' => $this->score($name, $id, [], $query, $tokens) + 30,
            ];
        }

        return $items;
    }

    /**
     * Приводит строки к формату элемента и отбрасывает несовпадения по словам.
     *
     * @param iterable<object> $rows
     * @param list<string> $tokens
     * @param callable(object):array{type:string,title:string,url:string,description:string,meta:list<string>,action:string} $map
     * @return list<array{type:string,title:string,url:string,description:string,meta:list<string>,action:string,_score:int}>
     */
    private function collect(iterable $rows, array $tokens, string $query, callable $map): array
    {
        $items = [];
        foreach ($rows as $row) {
            $item = $map($row);
            $score = $this->score(
                $item['title'],
                $item['description'],
                $item['meta'],
                $query,
                $tokens
            );
            if ($score <= 0) {
                continue;
            }
            $item['_score'] = $score;
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $columns
     */
    private function likeQuery(string $table, array $columns, array $tokens): Builder
    {
        $query = $this->database->connection()->table($table);
        // MySQL/MariaDB сравнивают LIKE без учёта регистра по collation, а в
        // SQLite кириллица регистрозависима: используем общую lower-функцию.
        $lower = $this->database->connection()->getDriverName() === 'sqlite' ? 'mjd_lower' : 'LOWER';
        $query->where(static function ($nested) use ($columns, $tokens, $lower): void {
            $first = true;
            foreach ($tokens as $token) {
                foreach ($columns as $column) {
                    $value = '%' . $token . '%';
                    if ($first) {
                        $nested->whereRaw($lower . '(' . $column . ') LIKE ?', [$value]);
                        $first = false;
                    } else {
                        $nested->orWhereRaw($lower . '(' . $column . ') LIKE ?', [$value]);
                    }
                }
            }
        });

        return $query;
    }

    /**
     * @param array<string,list<array{type:string,title:string,url:string,description:string,meta:list<string>,action:string,_score:int}>> $sections
     * @param array<string,int> $counts
     * @param list<array{type:string,title:string,url:string,description:string,meta:list<string>,action:string,_score:int}> $items
     */
    private function addSection(array &$sections, array &$counts, string $name, array $items, int $limit): void
    {
        if ($items === []) {
            return;
        }

        usort($items, static function (array $a, array $b): int {
            if ($a['_score'] === $b['_score']) {
                return strnatcasecmp($a['title'], $b['title']);
            }

            return $b['_score'] <=> $a['_score'];
        });

        $items = array_slice($items, 0, $limit);
        foreach ($items as &$item) {
            unset($item['_score']);
        }
        unset($item);

        $sections[$name] = $items;
        $counts[$name] = count($items);
    }

    /**
     * @param list<string> $meta
     * @param list<string> $tokens
     */
    private function score(string $title, string $description, array $meta, string $query, array $tokens): int
    {
        $titleLower = mb_strtolower($title);
        $haystack = $titleLower . ' ' . mb_strtolower($description . ' ' . implode(' ', $meta));
        foreach ($tokens as $token) {
            if (!str_contains($haystack, $token)) {
                return 0;
            }
        }

        $score = 0;
        if ($titleLower === $query) {
            $score += 200;
        } elseif (str_starts_with($titleLower, $query)) {
            $score += 140;
        } elseif (str_contains($titleLower, $query)) {
            $score += 100;
        }
        foreach ($tokens as $token) {
            $score += str_contains($titleLower, $token) ? 20 : 5;
        }

        return $score;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $normalized): array
    {
        $tokens = preg_split('/\s+/u', $normalized) ?: [];
        $tokens = array_values(array_unique(array_filter(
            $tokens,
            static fn (string $token): bool => $token !== ''
        )));

        return array_slice($tokens, 0, self::MAX_TOKENS);
    }

    /**
     * @return array{
     *     ok:bool,
     *     query:string,
     *     total:int,
     *     counts:array<string,int>,
     *     sections:array<string,list<array{type:string,title:string,url:string,description:string,meta:list<string>,action:string}>>,
     *     message:string
     * }
     */
    private function emptyResult(string $query, string $message): array
    {
        return [
            'ok' => true,
            'query' => $query,
            'total' => 0,
            'counts' => [],
            'sections' => [],
            'message' => $message,
        ];
    }
}
