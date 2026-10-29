<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Http\Request;
use LaIOT\Database\Migrator;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use LaIOT\Services\History\PropertyHistoryService;
use PHPUnit\Framework\TestCase;

/**
 * История свойства на Yoyo: сервис расчёта и компонент вместо JSON-роута.
 *
 * 2026-10-27 — тест заменил HistoryChartTest после переноса на Yoyo.
 * 2026-10-27 — начальная модель Users не входит в фикстуру датчика.
 */
final class PropertyHistoryTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        Yoyo::request()->reset();

        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Администратор', 'is_admin' => true]);
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'cache' => ['driver' => 'array'],
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $this->app->boot();
        (new Migrator($this->app->database(), $this->app->root() . '/database/migrations'))->migrate();
        SessionFixture::authenticate($this->app);

        $db = $this->app->database()->connection();
        foreach (['methods', 'properties', 'classes'] as $table) {
            $db->table($table)->delete();
        }
        $now = date('Y-m-d H:i:s');
        $db->table('classes')->insert([
            'id' => 1, 'title' => 'Датчик', 'parent_id' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $db->table('objects')->insert([
            'id' => 1, 'title' => 'Кухня', 'class_id' => 1, 'location_id' => 0,
            'description' => '', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $db->table('properties')->insert([
            'id' => 1, 'class_id' => 1, 'title' => 'temperature', 'description' => '',
            'data_type' => 'float', 'keep_history' => 1, 'on_change' => '',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $db->table('property_values')->insert([
            'object_id' => 1, 'property_id' => 1, 'value' => '23.5',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach ([
            ['value' => '21', 'created_at' => date('Y-m-d H:i:s', time() - 10 * 86400)],
            ['value' => '25', 'created_at' => date('Y-m-d H:i:s', time() - 3600)],
            ['value' => '23.5', 'created_at' => date('Y-m-d H:i:s', time() - 60)],
        ] as $row) {
            $db->table('property_history')->insert($row + [
                'object_id' => 1,
                'property_id' => 1,
                'updated_at' => $row['created_at'],
            ]);
        }
    }

    protected function tearDown(): void
    {
        Yoyo::request()->reset();
        unset($_SERVER['HTTP_HX_REQUEST'], $_SERVER['REQUEST_METHOD']);
        $_REQUEST = [];
        parent::tearDown();
    }

    public function testServiceReturnsStatsAndChartForRange(): void
    {
        $service = $this->app->container()->make(PropertyHistoryService::class);

        $all = $service->for(1, 1, 'all');
        self::assertNotNull($all);
        self::assertSame('Кухня', $all['object']['title']);
        self::assertSame('23.5', $all['current']);
        self::assertTrue($all['is_numeric']);
        self::assertSame(3, $all['stats']['count']);
        self::assertEquals(21.0, $all['stats']['min']);
        self::assertEquals(25.0, $all['stats']['max']);
        self::assertEquals(23.17, $all['stats']['avg']);
        self::assertCount(3, $all['chart']);

        $day = $service->for(1, 1, '24h');
        self::assertNotNull($day);
        self::assertSame(2, $day['stats']['count']);
        self::assertNull($service->for(99, 1));
    }

    public function testObjectPropertiesRenderYoyoHistoryComponent(): void
    {
        $response = $this->app->handle(Request::create('/objects/1/edit', 'GET', ['tab' => 'properties']));

        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('yoyo:name="property-history"', $html);
        self::assertStringContainsString('hx-get="open"', $html);
        self::assertStringContainsString('property-history', $html);
    }

    public function testYoyoActionOpensDrawerWithHistory(): void
    {
        $_REQUEST = [
            'component' => 'property-history/open',
            'yoyo-id' => 'test-history',
            'objectId' => 1,
            'propertyId' => 1,
            'title' => 'Кухня.temperature',
            'range' => '24h',
            'open' => 0,
        ];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        Yoyo::request()->mock($_REQUEST, $_SERVER);

        $response = $this->app->handle(Request::create('/yoyo', 'GET', $_REQUEST));

        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('История значения', $html);
        self::assertStringContainsString('Кухня.temperature', $html);
        self::assertStringContainsString('md-history-panel', $html);
    }
}
