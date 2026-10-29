<?php

declare(strict_types=1);

namespace LaIOT\Services\Docs;

use DOMDocument;
use DOMElement;
use DOMXPath;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\MarkdownConverter;

/**
 * Безопасный рендер Markdown для базы знаний.
 *
 * HTML из документа не исполняется (`html_input=strip`), небезопасные ссылки
 * запрещены. Относительные ссылки на другие `.md` переписываются в маршрут
 * базы знаний, картинки и прочие файлы — в `/docs/...`. Заголовки получают
 * id, по ним строится оглавление.
 *
 * 2026-10-28 — блоки кода получили кнопки копирования в базе знаний.
 * 2026-10-26 — модуль knowledge_base на новом ядре LaIOT.
 */
final class MarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $this->converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
    }

    /**
     * @param array<string,true> $documentIds известные id документов
     * @return array{html:string,toc:list<array{level:int,id:string,title:string}>}
     */
    public function render(string $markdown, string $docId, array $documentIds): array
    {
        $html = (string)$this->converter->convert($markdown);
        if (trim($html) === '') {
            return ['html' => '', 'toc' => []];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'
            . '<div id="kb-root">' . $html . '</div></body></html>',
            LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return ['html' => $html, 'toc' => []];
        }

        $xpath = new DOMXPath($dom);
        $root = $xpath->query('//div[@id="kb-root"]')->item(0);
        if (!$root instanceof DOMElement) {
            return ['html' => $html, 'toc' => []];
        }

        $toc = [];
        $slugs = [];
        foreach ($xpath->query('.//h1|.//h2|.//h3|.//h4', $root) ?: [] as $heading) {
            if (!$heading instanceof DOMElement) {
                continue;
            }
            $id = $heading->getAttribute('id');
            if ($id === '') {
                $id = $this->slug(trim($heading->textContent), $slugs);
                $heading->setAttribute('id', $id);
            }
            $level = (int)substr($heading->nodeName, 1);
            if ($level === 2 || $level === 3) {
                $toc[] = [
                    'level' => $level,
                    'id' => $id,
                    'title' => trim($heading->textContent),
                ];
            }
        }

        foreach ($xpath->query('.//a[@href]', $root) ?: [] as $link) {
            if (!$link instanceof DOMElement) {
                continue;
            }
            $url = $this->resolve((string)$link->getAttribute('href'), $docId, $documentIds, true);
            if ($url !== null) {
                $link->setAttribute('href', $url);
            }
        }
        foreach ($xpath->query('.//img[@src]', $root) ?: [] as $image) {
            if (!$image instanceof DOMElement) {
                continue;
            }
            $url = $this->resolve((string)$image->getAttribute('src'), $docId, $documentIds, false);
            if ($url !== null) {
                $image->setAttribute('src', $url);
            }
        }

        foreach ($xpath->query('.//pre[code]', $root) ?: [] as $pre) {
            if (!$pre instanceof DOMElement) {
                continue;
            }
            $wrapper = $dom->createElement('div');
            $wrapper->setAttribute('class', 'md-code-sample');
            $head = $dom->createElement('div');
            $head->setAttribute('class', 'md-code-sample__head');
            $button = $dom->createElement('button', 'Копировать');
            $button->setAttribute('type', 'button');
            $button->setAttribute('class', 'md-code-sample__copy');
            $button->setAttribute('data-kb-copy', '');
            $button->setAttribute('aria-live', 'polite');
            $head->appendChild($button);
            $pre->parentNode?->replaceChild($wrapper, $pre);
            $wrapper->appendChild($head);
            $wrapper->appendChild($pre);
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return ['html' => $output, 'toc' => $toc];
    }

    /**
     * @param array<string,true> $documentIds
     */
    private function resolve(string $url, string $docId, array $documentIds, bool $isLink): ?string
    {
        $url = trim($url);
        if ($url === '' || $url[0] === '#' || $url[0] === '/') {
            return null;
        }
        if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|//)~i', $url)) {
            return null;
        }

        [$path, $fragment] = array_pad(explode('#', $url, 2), 2, '');
        $path = rawurldecode($path);
        if ($path === '') {
            return null;
        }
        $target = $this->normalize(dirname($docId) . '/' . $path);
        if ($target === null) {
            return null;
        }

        $suffix = $fragment !== '' ? '#' . rawurlencode($fragment) : '';
        if ($isLink && preg_match('~\.md$~i', $path)) {
            $id = (string)preg_replace('~\.md$~i', '', $target);
            if (isset($documentIds[$id])) {
                return '/knowledge-base/' . $this->encodePath($id) . $suffix;
            }
        }

        return '/docs/' . $this->encodePath($target) . $suffix;
    }

    private function normalize(string $path): ?string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($parts === []) {
                    return null;
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return $parts === [] ? null : implode('/', $parts);
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /**
     * @param array<string,bool> $used
     */
    private function slug(string $title, array &$used): string
    {
        $slug = (string)preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($title));
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'section';
        }
        $base = $slug;
        $index = 2;
        while (isset($used[$slug])) {
            $slug = $base . '-' . $index;
            $index++;
        }
        $used[$slug] = true;

        return $slug;
    }
}
