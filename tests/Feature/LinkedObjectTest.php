<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use LaIOT\Services\LinkedObject\LinkedObjectService;
use PHPUnit\Framework\TestCase;

/**
 * Ядровой linked-object: поиск, контекст класса, API и Alpine-компонент.
 *
 * 2026-10-28 — варианты и контекст редактора проверяются через Yoyo.
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 */
final class LinkedObjectTest extends TestCase
{
    protected function tearDown(): void
    {
        Yoyo::request()->reset();
        unset($_SERVER['HTTP_HX_REQUEST']);
        $_REQUEST = [];
    }

    public function testSearchAndContext(): void
    {
        $app = $this->boot();
        $service = $app->container()->make(LinkedObjectService::class);

        $found = $service->search('Кух');
        self::assertCount(1, $found);
        self::assertSame('Кухня', $found[0]['title']);
        self::assertSame('Комната', $found[0]['class']);

        $context = $service->context('Кухня');
        self::assertNotNull($context);
        self::assertSame('Комната', $context['class']);
        self::assertSame('temperature', $context['properties'][0]['title']);
        self::assertSame('onAlarm', $context['methods'][0]['title']);

        self::assertNull($service->context('Нет такого'));
        self::assertSame([], $service->search('  '));
        self::assertSame('Кухня', $service->suggestions()[0]['title']);
        self::assertSame('Кухня', $service->suggestions('Комната')[0]['title']);
        self::assertSame(1, $context['id']);
    }

    public function testApiRequiresSessionAndReturnsJson(): void
    {
        $app = $this->boot(true);
        $response = $app->handle(Request::create('/api/v1/panel/linked-object?q=Кух', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]));
        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string)$response->getContent(), true);
        self::assertTrue((bool)($payload['ok'] ?? false));
        self::assertSame('Кухня', $payload['data']['objects'][0]['title'] ?? '');

        $context = $app->handle(Request::create('/api/v1/panel/linked-object?object=Кухня', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]));
        self::assertSame(200, $context->getStatusCode());
        self::assertSame('temperature', json_decode((string)$context->getContent(), true)['data']['properties'][0]['title'] ?? '');

        $guest = $this->boot(false)->handle(Request::create('/api/v1/panel/linked-object?q=Кух', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]));
        self::assertSame(401, $guest->getStatusCode());
    }

    public function testComponentIsRegisteredInUiKit(): void
    {
        $app = $this->boot(true);
        $page = $app->handle(Request::create('/ui-kit', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        $html = (string)$page->getContent();
        self::assertStringContainsString('md-linked-object', $html);
        self::assertStringContainsString('mdLinkedObject', $html);
        self::assertStringContainsString('yoyo:name="linked-object-data"', $html);
        self::assertStringContainsString('role="combobox"', $html);
    }

    public function testYoyoSearchReturnsContextAndKeepsRequestSequence(): void
    {
        $app = $this->boot(true);
        $params = [
            'component' => 'linked-object-data/render', 'yoyo-id' => 'linked-fixture',
            'query' => 'Кух', 'object' => 'Кухня', 'sequence' => 7,
        ];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        Yoyo::request()->mock($params, $_SERVER);
        $response = $app->handle(Request::create('/yoyo', 'GET', $params));
        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('temperature', $html);
        self::assertStringContainsString('onAlarm', $html);
        self::assertStringContainsString('value="7"', $html);
    }

    public function testYoyoDataRequiresAuthentication(): void
    {
        $response = $this->boot()->handle(Request::create('/yoyo', 'GET', [
            'component' => 'linked-object-data/render', 'object' => 'Кухня',
        ]));
        self::assertSame(302, $response->getStatusCode());
    }

    private function boot(bool $login = false): Application
    {
        Yoyo::request()->reset();
        $session = new ArraySession();
        if ($login) {
            $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Администратор', 'is_admin' => true]);
        }
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'cache' => ['driver' => 'array'],
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        $schema = $app->database()->schema();
        $schema->create('classes', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title', 191);
            $table->integer('parent_id')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('objects', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('class_id')->default(0);
            $table->integer('location_id')->default(0);
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('properties', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('class_id')->default(0);
            $table->integer('object_id')->default(0);
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('data_type', 32)->default('string');
            $table->boolean('keep_history')->default(false);
            $table->string('on_change', 255)->default('');
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('methods', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('class_id')->default(0);
            $table->integer('object_id')->default(0);
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->text('code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $connection = $app->database()->connection();
        $connection->table('classes')->insert(['title' => 'Комната', 'created_at' => '2026-10-26 12:00:00', 'updated_at' => '2026-10-26 12:00:00']);
        $connection->table('objects')->insert([
            'class_id' => 1, 'location_id' => 0, 'title' => 'Кухня', 'description' => '',
            'created_at' => '2026-10-26 12:00:00', 'updated_at' => '2026-10-26 12:00:00',
        ]);
        $connection->table('properties')->insert([
            'class_id' => 1, 'title' => 'temperature', 'description' => '', 'data_type' => 'number',
            'keep_history' => 0, 'on_change' => '', 'created_at' => '2026-10-26 12:00:00', 'updated_at' => '2026-10-26 12:00:00',
        ]);
        $connection->table('methods')->insert([
            'class_id' => 1, 'title' => 'onAlarm', 'description' => '', 'code' => 'return 1;',
            'created_at' => '2026-10-26 12:00:00', 'updated_at' => '2026-10-26 12:00:00',
        ]);

        SessionFixture::authenticate($app);

        return $app;
    }
}
