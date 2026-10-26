<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Docs\KnowledgeBase;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use Symfony\Component\HttpFoundation\Response;

/**
 * База знаний в alpha-layout.
 *
 * Читает `docs/**\/*.md` через core-сервис `KnowledgeBase`: отдельной таблицы
 * нет, источники — те же Markdown-файлы, что у агентов.
 */
final readonly class KnowledgeBaseController extends Controller
{
    public function __construct(
        private Factory $view,
        private KnowledgeBase $docs,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $category = trim((string)$request->query->get('category', ''));
        $documents = [];
        foreach ($this->docs->search($search, $category) as $document) {
            $documents[] = $document + ['url' => self::url($document['id'])];
        }

        $content = $this->view->make('pages.knowledge_base.index', [
            'documents' => $documents,
            'categories' => $this->docs->categories(),
            'search' => $search,
            'category' => $category,
        ])->render();

        return $this->panel->render(new PanelPage('База знаний', $content, 'knowledge_base'));
    }

    public function show(string $doc): Response
    {
        $document = $this->docs->find($doc);
        if ($document === null) {
            $content = $this->view->make('pages.knowledge_base.missing', [
                'doc' => $doc,
            ])->render();

            return $this->panel->render(new PanelPage('База знаний', $content, 'knowledge_base'), 404);
        }

        $content = $this->view->make('pages.knowledge_base.show', [
            'document' => $document,
        ])->render();

        return $this->panel->render(new PanelPage($document['title'], $content, 'knowledge_base'));
    }

    private static function url(string $id): string
    {
        return '/module/knowledge_base/' . implode('/', array_map('rawurlencode', explode('/', $id)));
    }
}
