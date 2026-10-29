<?php

declare(strict_types=1);

namespace LaIOT\Yoyo;

use Clickfwd\Yoyo\Component;
use LaIOT\Services\Docs\KnowledgeBase;

/**
 * Каталог документации: общий поиск и категории без перезагрузки страницы.
 *
 * 2026-10-28 — ссылки на статьи сохраняют запрос и выбранную категорию.
 */
final class KnowledgeCatalog extends Component
{
    public string $search = '';

    public string $category = '';

    /** @var list<string> Поиск приходит из input: hx-vals не должен перекрывать его старым значением. */
    protected $props = ['category'];

    private KnowledgeBase $docs;

    public function mount(KnowledgeBase $docs): void
    {
        $this->docs = $docs;
    }

    public function chooseCategory(string $encoded): void
    {
        $category = base64_decode($encoded, true);
        if ($category === '' || (is_string($category) && isset($this->docs->categories()[$category]))) {
            $this->category = $category;
        }
    }

    public function reset(): void
    {
        $this->search = '';
        $this->category = '';
    }

    public function render(): mixed
    {
        $categories = $this->docs->categories();
        $documents = [];
        $context = http_build_query(['q' => $this->search, 'category' => $this->category]);
        foreach ($this->docs->search($this->search, $this->category) as $document) {
            $documents[] = $document + [
                'url' => '/knowledge-base/' . implode('/', array_map('rawurlencode', explode('/', $document['id']))) . '?' . $context,
            ];
        }

        return $this->view('knowledge-catalog', [
            'documents' => $documents,
            'categories' => $categories,
            'total' => array_sum(array_column($categories, 'count')),
            'search' => $this->search,
            'category' => $this->category,
        ]);
    }
}
