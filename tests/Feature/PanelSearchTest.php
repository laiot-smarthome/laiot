<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use LaIOT\Services\Search\PanelSearch;
use PHPUnit\Framework\TestCase;

/**
 * Поиск по новому ядру: сервис, API `/api/v1/panel/search` и drawer в alpha-layout.
 *
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 * 2026-10-27 — точное совпадение не теряется за предварительным лимитом.
 */
final class PanelSearchTest extends TestCase
{
    public function testSearchGroupsResultsBySection(): void
    {
        $app = $this->boot()[0];
        $search = $app->container()->make(PanelSearch::class);

        $byClass = $search->search('Датчик');
        self::assertTrue($byClass['ok']);
        self::assertGreaterThan(0, $byClass['total']);
        self::assertArrayHasKey('Классы', $byClass['sections']);
        self::assertArrayHasKey('Свойства', $byClass['sections']);
        self::assertArrayHasKey('Методы', $byClass['sections']);
        self::assertSame(
            '/classes/1/edit',
            $byClass['sections']['Классы'][0]['url']
        );
        self::assertSame(
            '/classes/1/edit?tab=properties',
            $byClass['sections']['Свойства'][0]['url']
        );
        self::assertSame(
            '/classes/1/edit?tab=methods',
            $byClass['sections']['Методы'][0]['url']
        );
        // Регистр не должен зависеть от collation: SQLite и MariaDB отвечают
        // одинаково на запрос в нижнем регистре.
        self::assertGreaterThan(0, $search->search('датчик')['total']);

        $byObject = $search->search('Кухня');
        self::assertArrayHasKey('Объекты', $byObject['sections']);
        self::assertArrayHasKey('Локации', $byObject['sections']);
        self::assertSame('object', $byObject['sections']['Объекты'][0]['type']);

        $sections = $search->search('События');
        self::assertArrayHasKey('Разделы', $sections['sections']);
        self::assertSame('/events', $sections['sections']['Разделы'][0]['url']);
    }

    public function testSearchMatchesEveryToken(): void
    {
        $app = $this->boot()[0];
        $search = $app->container()->make(PanelSearch::class);

        $result = $search->search('Датчик температуры');
        self::assertGreaterThan(0, $result['total']);

        self::assertSame(0, $search->search('Датчик холодильник')['total']);
    }

    public function testSearchRanksAllCandidatesAndIncludesClassMetadata(): void
    {
        $app = $this->boot()[0];
        $connection = $app->database()->connection();
        for ($i = 0; $i < 70; $i++) {
            $connection->table('scripts')->insert([
                'title' => sprintf('AAA %03d', $i), 'description' => 'датчик',
            ]);
        }
        $connection->table('scripts')->insert(['title' => 'Датчик температуры', 'description' => '']);
        $search = $app->container()->make(PanelSearch::class);
        $result = $search->search('датчик температуры', 1);
        self::assertSame('Датчик температуры', $result['sections']['Скрипты'][0]['title']);

        $connection->table('properties')->insert([
            'class_id' => 1, 'title' => 'humidity', 'description' => '',
        ]);
        $result = $search->search('датчик humidity');
        self::assertSame('humidity', $result['sections']['Свойства'][0]['title']);
    }

    public function testShortQueryAndLimit(): void
    {
        $app = $this->boot()[0];
        $search = $app->container()->make(PanelSearch::class);

        $short = $search->search('Да');
        self::assertSame(0, $short['total']);
        self::assertStringContainsString('3', $short['message']);

        $limited = $search->search('Датчик', 1);
        foreach ($limited['sections'] as $items) {
            self::assertLessThanOrEqual(1, count($items));
        }
    }

    public function testEndpointReturnsPanelEnvelope(): void
    {
        [$app] = $this->boot();

        $response = $app->handle(Request::create('/api/v1/panel/search', 'GET', [
            'q' => 'Датчик',
            'limit' => 5,
        ], [], [], ['HTTP_ACCEPT' => 'application/json']));

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            self::fail('Ответ поиска должен быть JSON-объектом.');
        }
        self::assertTrue($payload['ok'] ?? false);
        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            self::fail('Ответ поиска должен содержать data.');
        }
        self::assertGreaterThan(0, (int)($data['total'] ?? 0));
        self::assertArrayHasKey('sections', $data);
        self::assertSame('Датчик', $data['query'] ?? null);
    }

    public function testEndpointRequiresAdminSession(): void
    {
        $session = new ArraySession();
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $session,
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();

        $response = $app->handle(Request::create('/api/v1/panel/search', 'GET', [
            'q' => 'Датчик',
        ], [], [], ['HTTP_ACCEPT' => 'application/json']));

        self::assertSame(401, $response->getStatusCode());
        $payload = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            self::fail('Неавторизованный поиск должен вернуть JSON.');
        }
        self::assertFalse($payload['ok'] ?? true);
    }

    public function testAdminLayoutHasSearchDrawer(): void
    {
        [$app] = $this->boot();

        $response = $app->handle(Request::create('/admin', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('id="mdmGlobalSearchDrawer"', $html);
        self::assertStringContainsString('id="filter_modules"', $html);
        self::assertStringContainsString('id="filter_add"', $html);
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

        foreach ($this->schema() as $name => $definition) {
            $app->database()->schema()->create($name, $definition);
        }
        $this->seed($app);

        SessionFixture::authenticate($app);

        return [$app, $session];
    }

    /**
     * @return array<string, callable(Blueprint):void>
     */
    private function schema(): array
    {
        return [
            'classes' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title');
                $table->integer('parent_id')->default(0);
                $table->timestamps();
                $table->softDeletes();
            },
            'objects' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title');
                $table->integer('class_id')->default(0);
                $table->integer('location_id')->default(0);
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
            },
            'properties' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('class_id')->default(0);
                $table->integer('object_id')->default(0);
                $table->string('title');
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
            },
            'methods' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('class_id')->default(0);
                $table->integer('object_id')->default(0);
                $table->string('title');
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
            },
            'scripts' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title');
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
            },
            'users' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username');
                $table->string('name')->default('');
                $table->string('password')->default('');
                $table->boolean('is_admin')->default(false);
                $table->timestamps();
                $table->softDeletes();
            },
            'locations' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title');
                $table->integer('priority')->default(0);
                $table->timestamps();
                $table->softDeletes();
            },
        ];
    }

    private function seed(Application $app): void
    {
        $connection = $app->database()->connection();
        $now = date('Y-m-d H:i:s');

        $connection->table('classes')->insert([
            'id' => 1, 'title' => 'Датчик', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $connection->table('objects')->insert([
            'id' => 1, 'title' => 'Кухня', 'class_id' => 1, 'location_id' => 1,
            'description' => 'Датчик температуры и света', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $connection->table('properties')->insert([
            'id' => 1, 'class_id' => 1, 'title' => 'temperature',
            'description' => 'Датчик температуры', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $connection->table('methods')->insert([
            'id' => 1, 'class_id' => 1, 'title' => 'onTemperature',
            'description' => 'Обработчик датчика', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $connection->table('scripts')->insert([
            'id' => 1, 'title' => 'Утренний сценарий',
            'description' => 'Включает свет на кухне', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $connection->table('users')->insert([
            'id' => 1, 'username' => 'admin', 'name' => 'Администратор',
            'password' => password_hash('secret', PASSWORD_DEFAULT), 'is_admin' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $connection->table('locations')->insert([
            'id' => 1, 'title' => 'Кухня', 'priority' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
