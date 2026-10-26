<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Http\Request;
use MajorDoMo\Docs\KnowledgeBase;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * База знаний: индекс docs, front matter, Markdown, ссылки и страницы модуля.
 */
final class KnowledgeBaseTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mydomo_kb_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/mydomo', 0777, true);
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

## Раздел

Опасный HTML <script>alert(1)</script> вырезан.

| A | B |
|---|---|
| 1 | 2 |

[Другой документ](user/commands.md)

[Плохая ссылка](javascript:alert(1))

![Картинка](img/scheme.png)
MD);
        file_put_contents($this->root . '/mydomo/README.md', <<<'MD'
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
        self::assertStringNotContainsString('<script>', $document['html']);
        self::assertStringNotContainsString('javascript:', $document['html']);
        self::assertStringContainsString('/module/knowledge_base/user/commands', $document['html']);
        self::assertStringContainsString('/docs/img/scheme.png', $document['html']);
        self::assertNotSame([], $document['toc']);
        self::assertSame('Раздел', $document['toc'][0]['title']);
        self::assertSame('раздел', $document['toc'][0]['id']);
    }

    public function testSearchAndTraversalProtection(): void
    {
        $docs = new KnowledgeBase($this->root);

        self::assertCount(1, $docs->search('цепочка'));
        self::assertSame('mydomo/README', $docs->search('цепочка')[0]['id']);
        self::assertSame([], $docs->search('ничего-нет'));
        self::assertNull($docs->find('../README'));
        self::assertNull($docs->find('nope'));
    }

    public function testModulePagesRenderDocs(): void
    {
        [$app] = $this->boot();

        $index = $app->handle(Request::create('/module/knowledge_base', 'GET'));
        self::assertSame(200, $index->getStatusCode());
        self::assertStringContainsString('База знаний', (string)$index->getContent());

        $page = $app->handle(Request::create('/module/knowledge_base/mydomo/functions', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        $html = (string)$page->getContent();
        self::assertStringContainsString('MyDoMo — функции, обёртки и CLI', $html);
        self::assertStringContainsString('md-prose', $html);
        self::assertStringContainsString('/module/events', $html);

        $missing = $app->handle(Request::create('/module/knowledge_base/nope', 'GET'));
        self::assertSame(404, $missing->getStatusCode());
        self::assertStringContainsString('Материал не найден', (string)$missing->getContent());
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
            'log' => 'php://stderr',
            'session' => $session,
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();

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
