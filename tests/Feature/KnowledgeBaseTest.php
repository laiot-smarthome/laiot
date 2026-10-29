<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use LaIOT\Services\Docs\KnowledgeBase;
use PHPUnit\Framework\TestCase;

/**
 * База знаний: индекс docs, front matter, Markdown, ссылки и страницы модуля.
 *
 * 2026-10-28 — поиск и категории через Yoyo, формат даты и возврат к выдаче.
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 */
final class KnowledgeBaseTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        Yoyo::request()->reset();
        $this->root = sys_get_temp_dir() . '/laiot_kb_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/laiot', 0777, true);
        mkdir($this->root . '/user', 0777, true);
        file_put_contents($this->root . '/README.md', <<<'MD'
---
title: Главная база
audience: user, agent
category: core
description: Точка входа.
order: 1
tags: база, тест
---

# Главная база

Вводный текст.

```bash
php bin/laiot about
```

## Раздел

Опасный HTML <script>alert(1)</script> вырезан.

| A | B |
|---|---|
| 1 | 2 |

[Другой документ](user/commands.md)

[Плохая ссылка](javascript:alert(1))

![Картинка](img/scheme.png)
MD);
        file_put_contents($this->root . '/laiot/README.md', <<<'MD'
---
title: Документация ядра
audience: agent
category: core
description: Про ядро.
---

# Документация ядра

## Цепочка

Текст.
MD);
        file_put_contents($this->root . '/user/commands.md', <<<'MD'
---
title: Команды
audience: user
category: user
description: Пользовательские команды.
---

# Команды

## Список

Текст.
MD);
    }

    protected function tearDown(): void
    {
        Yoyo::request()->reset();
        unset($_SERVER['HTTP_HX_REQUEST'], $_SERVER['REQUEST_METHOD']);
        $_REQUEST = [];
        $this->remove($this->root);
    }

    public function testIndexParsesFrontMatterAndCategories(): void
    {
        $docs = new KnowledgeBase($this->root);

        $all = $docs->all();
        self::assertCount(3, $all);
        $byId = [];
        foreach ($all as $document) {
            $byId[$document['id']] = $document;
        }
        self::assertSame('Команды', $byId['user/commands']['title']);
        self::assertSame(['user'], $byId['user/commands']['audience']);
        self::assertSame('user', $byId['user/commands']['category']);
        self::assertSame('Пользователю', $byId['user/commands']['category_label']);

        $categories = $docs->categories();
        self::assertSame(2, $categories['core']['count']);
        self::assertSame('Ядро', $categories['core']['label']);
        self::assertSame(1, $categories['user']['count']);
    }

    public function testMarkdownIsRenderedSafelyWithTocAndLinks(): void
    {
        $document = (new KnowledgeBase($this->root))->find('README');

        self::assertNotNull($document);
        self::assertStringContainsString('<table>', $document['html']);
        self::assertStringContainsString('data-kb-copy', $document['html']);
        self::assertStringContainsString('php bin/laiot about', $document['html']);
        self::assertStringNotContainsString('<script>', $document['html']);
        self::assertStringNotContainsString('javascript:', $document['html']);
        self::assertStringContainsString('/knowledge-base/user/commands', $document['html']);
        self::assertStringContainsString('/docs/img/scheme.png', $document['html']);
        self::assertNotSame([], $document['toc']);
        self::assertSame('Раздел', $document['toc'][0]['title']);
        self::assertSame('раздел', $document['toc'][0]['id']);
    }

    public function testSearchAndTraversalProtection(): void
    {
        $docs = new KnowledgeBase($this->root);

        self::assertCount(1, $docs->search('цепочка'));
        self::assertSame('laiot/README', $docs->search('цепочка')[0]['id']);
        self::assertSame([], $docs->search('ничего-нет'));
        self::assertNull($docs->find('../README'));
        self::assertNull($docs->find('nope'));
    }

    public function testModulePagesRenderDocs(): void
    {
        [$app] = $this->boot();

        $index = $app->handle(Request::create('/knowledge-base', 'GET'));
        self::assertSame(200, $index->getStatusCode());
        self::assertStringContainsString('База знаний', (string)$index->getContent());

        // Тест читает реальный docs/: заголовок берём из сервиса, иначе любая
        // правка текста документа ломает проверку страницы, а не базу знаний.
        $docs = new KnowledgeBase(dirname(__DIR__, 2) . '/docs');
        $document = $docs->find('laiot/functions') ?? ($docs->all()[0] ?? null);
        self::assertNotNull($document, 'В docs/ нет ни одного Markdown-документа.');
        $url = '/knowledge-base/' . implode('/', array_map('rawurlencode', explode('/', $document['id'])));

        $page = $app->handle(Request::create($url, 'GET'));
        self::assertSame(200, $page->getStatusCode());
        $html = (string)$page->getContent();
        self::assertStringContainsString($document['title'], $html);
        self::assertStringContainsString('md-prose', $html);

        $missing = $app->handle(Request::create('/knowledge-base/nope', 'GET'));
        self::assertSame(404, $missing->getStatusCode());
        self::assertStringContainsString('Материал не найден', (string)$missing->getContent());
    }

    public function testCatalogCombinesSearchAndCategoryAndKeepsArticleContext(): void
    {
        [$app] = $this->boot();
        $app->container()->instance(KnowledgeBase::class, new KnowledgeBase($this->root));

        $response = $app->handle(Request::create('/knowledge-base?q=цепочка&category=core', 'GET'));
        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('Найдено: 1', $html);
        self::assertStringContainsString('Документация ядра</a>', $html);
        self::assertStringNotContainsString('Главная база</a>', $html);
        self::assertStringContainsString('Категории базы знаний', $html);
        self::assertStringContainsString('knowledge-catalog', $html);
        self::assertStringContainsString('category=core', $html);
        self::assertStringContainsString('q=%D1%86%D0%B5%D0%BF%D0%BE%D1%87%D0%BA%D0%B0', $html);
    }

    public function testArticleFormatsDateAndReturnsToFilteredCatalog(): void
    {
        [$app] = $this->boot();
        touch($this->root . '/README.md', mktime(14, 30, 0, 10, 28, 2026));
        clearstatcache();
        $app->container()->instance(KnowledgeBase::class, new KnowledgeBase($this->root));

        $response = $app->handle(Request::create('/knowledge-base/README?q=база&category=core', 'GET'));
        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('28.10.2026 14:30', $html);
        self::assertStringNotContainsString('28.10.2026,', $html);
        self::assertStringContainsString('/knowledge-base?q=%D0%B1%D0%B0%D0%B7%D0%B0&amp;category=core', $html);
        self::assertStringContainsString('Содержание статьи', $html);
        self::assertStringContainsString('href="#раздел"', $html);
    }

    public function testYoyoFiltersResetsAndShowsEmptyResults(): void
    {
        [$app, $session] = $this->boot();
        $app->container()->instance(KnowledgeBase::class, new KnowledgeBase($this->root));

        $update = static function (string $action, array $state, array $arguments = []) use ($app, $session): string {
            $_REQUEST = $state + [
                'component' => 'knowledge-catalog/' . $action,
                'yoyo-id' => 'test-knowledge-catalog',
                'actionArgs' => $arguments,
            ];
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['HTTP_HX_REQUEST'] = 'true';
            Yoyo::request()->mock($_REQUEST, $_SERVER);
            $response = $app->handle(Request::create('/yoyo', 'POST', [], [], [], [
                'HTTP_X_CSRF_TOKEN' => $session->csrfToken(),
            ]));
            self::assertSame(200, $response->getStatusCode());

            return (string)$response->getContent();
        };

        $html = $update('chooseCategory', ['search' => 'текст', 'category' => ''], [base64_encode('user')]);
        self::assertStringContainsString('Найдено: 1', $html);
        self::assertStringContainsString('Команды</a>', $html);
        self::assertStringNotContainsString('Документация ядра</a>', $html);

        $html = $update('render', ['search' => 'ничего-нет', 'category' => 'user']);
        self::assertStringContainsString('Материалы не найдены', $html);
        self::assertStringContainsString('Показать все материалы', $html);

        $html = $update('reset', ['search' => 'ничего-нет', 'category' => 'user']);
        self::assertStringContainsString('Найдено: 3', $html);
        self::assertStringContainsString('Главная база</a>', $html);
        self::assertStringContainsString('Команды</a>', $html);
    }

    /**
     * @return array{0:Application,1:ArraySession}
     */
    private function boot(): array
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Администратор', 'is_admin' => true]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'view_cache' => $this->root . '/views',
            'log' => 'php://stderr',
            'session' => $session,
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();

        SessionFixture::authenticate($app);

        return [$app, $session];
    }

    private function remove(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->remove($path . '/' . $entry);
        }
        @rmdir($path);
    }
}
