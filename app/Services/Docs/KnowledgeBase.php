<?php

declare(strict_types=1);

namespace LaIOT\Services\Docs;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * База знаний LaIOT: читает те же `docs/**\/*.md`, что и агенты.
 *
 * Отдельной таблицы нет: источник истины — Markdown с front matter.
 * Категория, аудитория и порядок берутся из front matter; если ключа нет —
 * из пути. Индекс строится на запрос и кэшируется в памяти процесса.
 *
 * 2026-10-28 — отдельная категория справочника консольных команд.
 * 2026-10-26 — сервис создан для модуля knowledge_base на новом ядре.
 */
final class KnowledgeBase
{
    private const MAX_LIMIT = 500;
    private const CATEGORY_LABELS = [
        'general' => 'Общее',
        'core' => 'Ядро',
        'console' => 'Консольные команды',
        'data' => 'Данные',
        'automation' => 'Автоматизация',
        'ui' => 'Интерфейс',
        'api' => 'API',
        'integration' => 'Интеграции',
        'migration' => 'Миграция',
        'user' => 'Пользователю',
        'deploy' => 'Развёртывание',
        'dev' => 'Разработка',
        'archive' => 'Архив',
        'agents' => 'Агентам',
        'todo' => 'Планы',
    ];

    /** @var array<string,array{id:string,title:string,description:string,category:string,category_label:string,audience:list<string>,tags:list<string>,order:int,path:string,updated_at:string}>|null */
    private ?array $documents = null;
    private readonly MarkdownRenderer $renderer;

    public function __construct(private readonly string $root)
    {
        $this->renderer = new MarkdownRenderer();
    }

    /**
     * @return list<array{id:string,title:string,description:string,category:string,category_label:string,audience:list<string>,tags:list<string>,order:int,path:string,updated_at:string}>
     */
    public function all(): array
    {
        return array_values($this->index());
    }

    /**
     * @return list<array{id:string,title:string,description:string,category:string,category_label:string,audience:list<string>,tags:list<string>,order:int,path:string,updated_at:string}>
     */
    public function search(string $query = '', string $category = ''): array
    {
        $query = trim($query);
        $tokens = $query === '' ? [] : array_values(array_filter(
            preg_split('/\s+/u', mb_strtolower($query)) ?: [],
            static fn (string $token): bool => $token !== ''
        ));
        $result = [];
        foreach ($this->index() as $document) {
            if ($category !== '' && $document['category'] !== $category) {
                continue;
            }
            if ($tokens !== []) {
                $content = $this->body($document['path']);
                $haystack = mb_strtolower(
                    $document['title'] . ' ' . $document['description'] . ' '
                    . implode(' ', $document['tags']) . ' ' . $content
                );
                foreach ($tokens as $token) {
                    if (!str_contains($haystack, $token)) {
                        continue 2;
                    }
                }
            }
            $result[] = $document;
        }

        return array_slice($result, 0, self::MAX_LIMIT);
    }

    /**
     * @return array{id:string,title:string,description:string,category:string,category_label:string,audience:list<string>,tags:list<string>,order:int,path:string,updated_at:string,markdown:string,html:string,toc:list<array{level:int,id:string,title:string}>}|null
     */
    public function find(string $id): ?array
    {
        $id = trim(str_replace('\\', '/', $id), '/');
        if ($id === '' || str_contains($id, '..')) {
            return null;
        }
        $documents = $this->index();
        if (!isset($documents[$id])) {
            return null;
        }

        $document = $documents[$id];
        $markdown = $this->body($document['path']);
        $markdown = $this->stripLeadingTitle($markdown, $document['title']);
        $ids = [];
        foreach ($documents as $key => $item) {
            $ids[$key] = true;
        }
        $rendered = $this->renderer->render($markdown, $id, $ids);

        return $document + [
            'markdown' => $markdown,
            'html' => $rendered['html'],
            'toc' => $rendered['toc'],
        ];
    }

    /**
     * @return array<string,array{label:string,count:int}>
     */
    public function categories(): array
    {
        $categories = [];
        foreach ($this->index() as $document) {
            $key = $document['category'];
            if (!isset($categories[$key])) {
                $categories[$key] = ['label' => $document['category_label'], 'count' => 0];
            }
            $categories[$key]['count']++;
        }
        uasort($categories, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return $categories;
    }

    /**
     * @return array<string,array{id:string,title:string,description:string,category:string,category_label:string,audience:list<string>,tags:list<string>,order:int,path:string,updated_at:string}>
     */
    private function index(): array
    {
        if ($this->documents !== null) {
            return $this->documents;
        }
        $root = rtrim(str_replace('\\', '/', $this->root), '/');
        $this->documents = [];
        if ($root === '' || !is_dir($root)) {
            return $this->documents;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            $relative = ltrim(substr($path, strlen($root)), '/');
            if ($relative === '' || str_contains($relative, '/.')) {
                continue;
            }
            $document = $this->parse($relative, $path);
            if ($document !== null) {
                $this->documents[$document['id']] = $document;
            }
        }

        uasort($this->documents, static function (array $a, array $b): int {
            $category = strnatcasecmp($a['category_label'], $b['category_label']);
            if ($category !== 0) {
                return $category;
            }
            if ($a['order'] !== $b['order']) {
                return $a['order'] <=> $b['order'];
            }

            return strnatcasecmp($a['title'], $b['title']);
        });

        return $this->documents;
    }

    /**
     * @return array{id:string,title:string,description:string,category:string,category_label:string,audience:list<string>,tags:list<string>,order:int,path:string,updated_at:string}|null
     */
    private function parse(string $relative, string $path): ?array
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $meta = [];
        if (preg_match('/^---\n(.*?)\n---\n/s', $content, $matches)) {
            foreach (preg_split('/\n/', $matches[1]) ?: [] as $line) {
                if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*):\s*(.*)$/', trim($line), $item)) {
                    $meta[$item[1]] = trim($item[2], " \t\"'");
                }
            }
            $content = (string)substr($content, strlen($matches[0]));
        }

        $id = (string)preg_replace('/\.md$/i', '', $relative);
        if ($id === '') {
            return null;
        }
        $title = trim((string)($meta['title'] ?? ''));
        if ($title === '' && preg_match('/^#\s+(.+)$/m', $content, $heading)) {
            $title = trim($heading[1]);
        }
        if ($title === '') {
            $title = basename($id);
        }
        $description = trim((string)($meta['description'] ?? ''));
        if ($description === '') {
            $paragraph = preg_split('/\n\s*\n/', trim($content)) ?: [];
            foreach ($paragraph as $candidate) {
                $candidate = trim($candidate);
                if ($candidate !== '' && !str_starts_with($candidate, '#')) {
                    $description = mb_substr(strip_tags($candidate), 0, 200);
                    break;
                }
            }
        }
        $category = trim((string)($meta['category'] ?? ''));
        if ($category === '') {
            $category = explode('/', $id)[0];
            if ($category === $id || $category === '') {
                $category = 'general';
            }
        }
        $audience = trim((string)($meta['audience'] ?? ''));
        $audiences = $audience === ''
            ? $this->defaultAudience($id)
            : array_values(array_filter(array_map('trim', explode(',', $audience))));
        $tags = trim((string)($meta['tags'] ?? ''));
        $tagList = $tags === ''
            ? []
            : array_values(array_unique(array_filter(array_map('trim', explode(',', $tags)))));

        return [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'category_label' => (string)($meta['category_label'] ?? self::CATEGORY_LABELS[$category] ?? ucfirst($category)),
            'audience' => $audiences === [] ? ['user', 'agent'] : $audiences,
            'tags' => $tagList,
            'order' => (int)($meta['order'] ?? 100),
            'path' => $path,
            'updated_at' => date('Y-m-d H:i', (int)filemtime($path)),
        ];
    }

    private function body(string $path): string
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return '';
        }
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        if (preg_match('/^---\n.*?\n---\n/s', $content, $matches)) {
            $content = (string)substr($content, strlen($matches[0]));
        }

        return trim($content);
    }

    private function stripLeadingTitle(string $markdown, string $title): string
    {
        if (preg_match('/^#\s+(.+)\n+/', $markdown, $matches) && trim($matches[1]) === $title) {
            return trim((string)substr($markdown, strlen($matches[0])));
        }

        return $markdown;
    }

    /**
     * @return list<string>
     */
    private function defaultAudience(string $id): array
    {
        if (str_starts_with($id, 'user/')) {
            return ['user'];
        }
        if (str_starts_with($id, 'agents/') || str_starts_with($id, 'deploy/')
            || str_starts_with($id, 'dev/') || str_starts_with($id, 'archive/')) {
            return ['agent'];
        }

        return ['user', 'agent'];
    }
}
