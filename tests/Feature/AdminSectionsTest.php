<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Http\Request;
use MajorDoMo\Database\Migrator;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Properties\PropertyValueService;
use PHPUnit\Framework\TestCase;

/**
 * Админка MyDoMo: новая реализация разделов на ядровых таблицах.
 *
 * Проверяет то, что нельзя увидеть в отдельных репозиториях: страницы
 * открываются на полной схеме, ссылки ведут на чистые URL, фильтры и вкладки
 * карточки работают, обзор показывает реальные данные.
 *
 * 2026-10-26 — тест добавлен вместе с переносом разделов на x-md и md-dash.
 */
final class AdminSectionsTest extends TestCase
{
    private function boot(): Application
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Администратор', 'is_admin' => true]);

        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'cache' => ['driver' => 'array'],
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        (new Migrator($app->database(), $app->root() . '/database/migrations'))->migrate();
        SessionFixture::authenticate($app);

        $connection = $app->database()->connection();
        $now = date('Y-m-d H:i:s');

        $connection->table('classes')->insert([
            ['id' => 1, 'title' => 'Датчик', 'parent_id' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'title' => 'Свет', 'parent_id' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'title' => 'Умный датчик', 'parent_id' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('locations')->insert([
            ['id' => 1, 'title' => 'Кухня', 'priority' => 10, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('properties')->insert([
            ['id' => 1, 'class_id' => 1, 'object_id' => 0, 'title' => 'temperature', 'data_type' => 'float', 'keep_history' => 1, 'on_change' => '', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'class_id' => 2, 'object_id' => 0, 'title' => 'state', 'data_type' => 'bool', 'keep_history' => 1, 'on_change' => '', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'class_id' => 1, 'object_id' => 3, 'title' => 'battery', 'data_type' => 'int', 'keep_history' => 0, 'on_change' => '', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('methods')->insert([
            ['id' => 1, 'class_id' => 1, 'title' => 'refresh', 'description' => 'Обновить', 'code' => '', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('objects')->insert([
            ['id' => 1, 'title' => 'Кухня', 'class_id' => 1, 'location_id' => 1, 'description' => 'Датчик на кухне', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'title' => 'Спальня', 'class_id' => 2, 'location_id' => 1, 'description' => 'Свет в спальне', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'title' => 'Гостиная', 'class_id' => 3, 'location_id' => 1, 'description' => 'Датчик в гостиной', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $values = $app->container()->make(PropertyValueService::class);
        $values->setValue(1, 1, '23.5', 'panel:test');
        $values->setValue(3, 1, '19', 'panel:test');
        $values->setValue(3, 3, '80', 'panel:test');

        return $app;
    }

    public function testDashboardShowsRealActivity(): void
    {
        $app = $this->boot();
        $response = $app->handle(Request::create('/admin', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('md-dash', $html);
        self::assertStringContainsString('Активность дома', $html);
        self::assertStringContainsString('Кухня.temperature', $html);
        self::assertStringContainsString('Очередь заданий', $html);
    }

    public function testObjectsLiveInsideClassTreeInsteadOfSeparateCatalog(): void
    {
        $app = $this->boot();

        $redirect = $app->handle(Request::create('/objects', 'GET'));
        self::assertSame(302, $redirect->getStatusCode());
        self::assertSame('/classes', $redirect->headers->get('Location'));

        $page = $app->handle(Request::create('/classes', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        $html = (string)$page->getContent();
        self::assertStringContainsString('Кухня', $html);
        self::assertStringContainsString('Спальня', $html);
        self::assertStringContainsString('Гостиная', $html);
        self::assertStringContainsString('Умный датчик', $html);
        self::assertStringContainsString('data-parent-id="1"', $html);
        self::assertStringContainsString('href="/objects/1/edit"', $html);
    }

    public function testObjectCardTabsShowClassDataAndHistory(): void
    {
        $app = $this->boot();

        $properties = $app->handle(Request::create('/objects/1/edit', 'GET', ['tab' => 'properties']));
        self::assertSame(200, $properties->getStatusCode());
        $propertiesHtml = (string)$properties->getContent();
        self::assertStringContainsString('Свойства и значения', $propertiesHtml);
        self::assertStringContainsString('temperature', $propertiesHtml);
        self::assertStringContainsString('23.5', $propertiesHtml);

        $history = $app->handle(Request::create('/objects/1/edit', 'GET', ['tab' => 'history']));
        self::assertSame(200, $history->getStatusCode());
        self::assertStringContainsString('23.5', (string)$history->getContent());

        $journal = $app->handle(Request::create('/objects/1/edit', 'GET', ['tab' => 'journal']));
        self::assertSame(200, $journal->getStatusCode());
        self::assertStringContainsString('Журнал событий', (string)$journal->getContent());
    }

    public function testSubclassInheritsPropertiesAndMethodsWithoutClassValues(): void
    {
        $app = $this->boot();

        $properties = $app->handle(Request::create('/classes/3/edit', 'GET', ['tab' => 'properties']));
        self::assertSame(200, $properties->getStatusCode());
        $propertiesHtml = (string)$properties->getContent();
        self::assertStringContainsString('temperature', $propertiesHtml);
        self::assertStringContainsString('от Датчик', $propertiesHtml);
        // Значение принадлежит объекту, а не классу: формы значения в карточке
        // класса быть не должно.
        self::assertStringNotContainsString('name="values[', $propertiesHtml);

        $methods = $app->handle(Request::create('/classes/3/edit', 'GET', ['tab' => 'methods']));
        $methodsHtml = (string)$methods->getContent();
        self::assertStringContainsString('refresh', $methodsHtml);
        self::assertStringContainsString('от Датчик', $methodsHtml);

        $object = $app->handle(Request::create('/objects/3/edit', 'GET', ['tab' => 'properties']));
        $objectHtml = (string)$object->getContent();
        self::assertStringContainsString('temperature', $objectHtml);
        self::assertStringContainsString('battery', $objectHtml);
        self::assertStringContainsString('объектное', $objectHtml);
        self::assertStringContainsString('19', $objectHtml);
        self::assertStringContainsString('80', $objectHtml);
    }

    public function testClassesPageShowsTreeWithObjects(): void
    {
        $app = $this->boot();
        $response = $app->handle(Request::create('/classes', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('md-tree-table', $html);
        self::assertStringContainsString('Датчик', $html);
        self::assertStringContainsString('href="/objects/1/edit"', $html);
    }

    public function testClassesTableHasOneBodyAndNoNestedTree(): void
    {
        $app = $this->boot();
        $html = (string)$app->handle(Request::create('/classes', 'GET'))->getContent();

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // x-md.table сам оборачивает слот body в tbody; вложенный tbody
        // ломает дерево и раскрытие подклассов в браузере.
        self::assertSame(1, $dom->getElementsByTagName('tbody')->length);
        self::assertGreaterThan(1, $dom->getElementsByTagName('tr')->length);
    }

    public function testDestructiveActionsUseSharedConfirmDialog(): void
    {
        $app = $this->boot();
        $response = $app->handle(Request::create('/classes', 'GET'));

        $html = (string)$response->getContent();
        self::assertStringContainsString('mdConfirmDialog', $html);
        self::assertStringContainsString('data-md-confirm=', $html);
        self::assertStringNotContainsString('onsubmit="return confirm', $html);
    }

    public function testCodeEditorIsLazyChunk(): void
    {
        $app = $this->boot();
        $response = $app->handle(Request::create('/scripts/create', 'GET'));

        $html = (string)$response->getContent();
        self::assertStringContainsString('data-code-editor', $html);
        // Чанк редактора не подключается тегом в разметке: его грузит загрузчик.
        self::assertStringNotContainsString('mydomo-codeeditor.js', $html);
    }

    public function testHistoryDrawerIsYoyoComponentOnObjectProperties(): void
    {
        $app = $this->boot();
        $response = $app->handle(Request::create('/objects/1/edit', 'GET', ['tab' => 'properties']));

        $html = (string)$response->getContent();
        self::assertStringContainsString('yoyo:name="property-history"', $html);
        self::assertStringContainsString('hx-get="open"', $html);
        self::assertStringNotContainsString('/api/v1/panel/history', $html);
    }

    public function testAdminPagesUseCleanUrlsOnly(): void
    {
        $app = $this->boot();

        foreach (['/admin', '/classes', '/classes/1/edit', '/classes/1/edit?tab=properties',
                     '/classes/1/edit?tab=methods', '/classes/1/edit?tab=objects',
                     '/objects/1/edit', '/objects/1/edit?tab=properties',
                     '/properties/1/edit', '/methods/1/edit',
                     '/locations', '/scripts', '/events', '/jobs', '/users', '/settings', '/journal',
                     '/errors', '/api', '/admin/modules', '/status', '/notifications'] as $url) {
            $response = $app->handle(Request::create($url, 'GET'));
            self::assertSame(200, $response->getStatusCode(), 'Страница ' . $url . ' должна открываться.');
            self::assertStringNotContainsString('"/module/', (string)$response->getContent(), 'Ссылка на legacy /module/ в ' . $url);
        }
    }
}
