<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use LaIOT\Services\Events\EventChainService;
use LaIOT\Services\Events\PropertyEventRepository;
use LaIOT\Services\Events\PropertyExecutionRepository;
use PHPUnit\Framework\TestCase;

/**
 * Цепочка события свойства: запись → история → строка журнала → OnChange-метод.
 *
 * Проверяется на SQLite в памяти, без циклов, очередей и внешних сервисов.
 *
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 * 2026-10-28 — цепочки, полное дерево, фильтры, страницы и ожидающие команды.
 */
final class CoreEventsTest extends TestCase
{
    use DrainsEvents;

    public function testChangeRunsOnChangeAndAppearsInJournal(): void
    {
        [$app] = $this->boot();

        self::assertTrue(sg('Кухня.temperature', '23.5'));
        $this->drainEvents($app);

        self::assertSame('23.5', gg('Кухня.temperature'));
        self::assertSame(1, $this->rowCount($app, 'property_history'));

        $events = $this->events($app);
        self::assertCount(2, $events);
        self::assertSame('property.changed', $events[0]['type']);
        self::assertSame('changed', $events[0]['status']);
        self::assertSame(0, $events[0]['depth']);
        self::assertSame('', $events[0]['old_value']);
        self::assertSame('23.5', $events[0]['new_value']);
        self::assertSame('handler.method', $events[1]['type']);
        self::assertSame('ok', $events[1]['status']);
        self::assertSame('onTemperature', $events[1]['handler']);
        self::assertSame((int)$events[0]['id'], (int)$events[1]['parent_id']);
        self::assertSame(1, $events[1]['depth']);

        $page = $app->handle(Request::create('/events', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        $html = (string)$page->getContent();
        self::assertStringContainsString('Кухня', $html);
        self::assertStringContainsString('Цепочек на странице: 1', $html);
        self::assertStringNotContainsString('md-call-flow__tree', $html);
        self::assertStringContainsString('Значение изменено', $html);
        // Форма фильтра передаёт option слотом — компонент x-md.select обязан
        // отрендерить их, а не только массив options.
        self::assertStringContainsString('<option value="handler.method"', $html);

        $detail = $app->handle(Request::create('/events?chain=' . $events[0]['id'], 'GET'));
        self::assertSame(200, $detail->getStatusCode());
        self::assertStringContainsString('Кухня.onTemperature', (string)$detail->getContent());
        self::assertStringContainsString('md-call-flow__tree', (string)$detail->getContent());
    }

    public function testWriteIsProcessedByEventsWorkerNotInRequest(): void
    {
        [$app] = $this->boot();

        self::assertTrue(sg('Кухня.temperature', '23.5'));

        // Запись только сохранила значение и задание: код и история ещё не
        // выполнялись, в очередях events лежит одно задание.
        self::assertSame(1, $this->pendingEvents($app));
        self::assertSame(0, $app->database()->connection()->table('property_events')->count());
        self::assertSame(0, $app->database()->connection()->table('property_history')->count());

        $this->drainEvents($app);

        self::assertSame(0, $this->pendingEvents($app));
        self::assertGreaterThan(0, $app->database()->connection()->table('property_events')->count());
        self::assertSame(1, $app->database()->connection()->table('property_history')->count());
    }

    public function testRepeatedValueRunsHandlerWithoutSecondHistoryRow(): void
    {
        $app = $this->boot()[0];

        self::assertTrue(sg('Кухня.temperature', '23.5'));
        self::assertTrue(sg('Кухня.temperature', '23.5'));
        $this->drainEvents($app);

        self::assertSame(1, $this->rowCount($app, 'property_history'));
        self::assertSame(1, $this->rowsWhereCount($app, 'property_events', 'type', 'property.changed'));
        self::assertSame(2, $this->rowsWhereCount($app, 'property_events', 'type', 'handler.method'));
    }

    public function testLoopStopsAtRepeatedPropertyAndSavesValue(): void
    {
        $app = $this->boot()[0];

        self::assertTrue(sg('Кухня.alarm', '0'));
        $this->drainEvents($app);

        self::assertSame('1', gg('Кухня.alarm'));
        self::assertSame('hot', gg('Кухня.temperature'));
        self::assertGreaterThanOrEqual(1, $this->rowsWhereCount($app, 'property_events', 'status', 'stopped'));

        $events = $this->events($app);
        self::assertLessThanOrEqual(6, count($events));
        foreach ($events as $event) {
            self::assertLessThanOrEqual(3, $event['depth']);
        }
        $stopped = $this->firstRowWhere($app, 'property_events', 'status', 'stopped');
        self::assertNotNull($stopped);
        self::assertStringContainsString('уже встречалось', (string)$stopped->message);

        $page = $app->handle(Request::create('/events', 'GET', ['status' => 'stopped']));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('Цикл остановлен', (string)$page->getContent());
    }

    public function testHandlerErrorDoesNotRollBackValue(): void
    {
        $app = $this->boot()[0];

        self::assertTrue(sg('Кухня.broken', 'x'));
        $this->drainEvents($app);

        self::assertSame('x', gg('Кухня.broken'));
        $handler = $this->firstRowWhere($app, 'property_events', 'handler', 'onBroken');
        self::assertNotNull($handler);
        self::assertSame('error', $handler->status);
        self::assertStringContainsString('boom', (string)$handler->message);
    }

    public function testMissingHandlerIsRecorded(): void
    {
        $app = $this->boot()[0];

        self::assertTrue(sg('Кухня.ghost', '1'));
        $this->drainEvents($app);

        $handler = $this->firstRowWhere($app, 'property_events', 'handler', 'missingMethod');
        self::assertNotNull($handler);
        self::assertSame('missing', $handler->status);
    }

    public function testPropertyIsResolvedInsideObjectClass(): void
    {
        $app = $this->boot()[0];

        self::assertTrue(sg('Кухня.temperature', '1'));
        self::assertTrue(sg('Лампа.temperature', '2'));
        self::assertTrue(sg('Глобальный.global', '3'));
        self::assertFalse(sg('Лампа.unknown', '2'));
        self::assertFalse(sg('НетОбъекта.temperature', '1'));
        $this->drainEvents($app);

        $rows = $app->database()->connection()->table('property_values')
            ->orderBy('object_id')
            ->get(['object_id', 'property_id', 'value']);
        self::assertCount(3, $rows);
        self::assertSame(1, (int)$rows[0]->property_id);
        self::assertSame(5, (int)$rows[1]->property_id);
        self::assertSame(6, (int)$rows[2]->property_id);
    }

    public function testHistoryRespectsKeepHistoryFlag(): void
    {
        $app = $this->boot()[0];

        self::assertTrue(sg('Кухня.alarm', '1'));
        $this->drainEvents($app);

        // У alarm история выключена; вложенная запись temperature её не включает
        // для самого alarm.
        self::assertSame(0, $app->database()->connection()->table('property_history')
            ->where('property_id', 2)
            ->count());
    }

    public function testObjectPropertiesWriteThroughChain(): void
    {
        [$app, $session] = $this->boot();

        self::assertTrue(sg('Кухня.temperature', '20'));
        $this->drainEvents($app);

        $response = $app->handle(Request::create('/objects/1/values', 'POST', [
            '_token' => $session->csrfToken(),
            'values' => [1 => '21'],
        ]));
        self::assertSame(302, $response->getStatusCode());
        $this->drainEvents($app);
        self::assertSame('21', gg('Кухня.temperature'));
        self::assertSame(2, $this->rowCount($app, 'property_history'));

        $event = $this->firstRowWhere($app, 'property_events', 'source', 'panel:objects');
        self::assertNotNull($event);
        self::assertSame('property.changed', $event->type);
        self::assertSame('21', $event->new_value);
    }

    public function testEventsTableFiltersViaYoyoAction(): void
    {
        $app = $this->boot()[0];
        self::assertTrue(sg('Кухня.temperature', '23.5'));
        self::assertTrue(sg('Кухня.alarm', '0'));
        $this->drainEvents($app);

        Yoyo::request()->reset();
        $_REQUEST = [
            'component' => 'events-table/render',
            'yoyo-id' => 'test-events',
            'search' => 'alarm',
            'type' => '',
            'status' => '',
        ];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        Yoyo::request()->mock($_REQUEST, $_SERVER);

        try {
            $response = $app->handle(Request::create('/yoyo', 'GET', $_REQUEST));
            self::assertSame(200, $response->getStatusCode());
            $html = (string)$response->getContent();
            self::assertStringContainsString('alarm', $html);
            self::assertStringNotContainsString('onTemperature', $html);
        } finally {
            Yoyo::request()->reset();
            unset($_SERVER['HTTP_HX_REQUEST'], $_SERVER['REQUEST_METHOD']);
            $_REQUEST = [];
        }
    }

    public function testSearchMatchesChildButKeepsTheWholeChainAndSeparateRuns(): void
    {
        [$app] = $this->boot();
        $events = $app->container()->make(PropertyEventRepository::class);
        $root = $events->record(['type' => 'property.changed', 'status' => 'changed', 'source' => 'mqtt', 'object_title' => 'Sensor', 'property_title' => 'motion']);
        $handler = $events->record(['parent_id' => $root, 'type' => 'handler.method', 'status' => 'ok', 'handler' => 'onMotion']);
        $events->record(['parent_id' => $handler, 'type' => 'module.delivery', 'status' => 'error', 'handler' => 'lamp', 'message' => 'unique failure']);
        $other = $events->record(['type' => 'property.changed', 'status' => 'changed', 'object_title' => 'Sensor', 'property_title' => 'motion']);
        $service = $app->container()->make(EventChainService::class);

        $listing = $service->listing('unique failure', 'module.delivery', 'error');
        self::assertCount(1, $listing['items']);
        self::assertSame($root, $listing['items'][0]['id']);
        self::assertSame(3, $listing['items'][0]['count']);
        self::assertSame('error', $listing['items'][0]['status']);
        self::assertCount(2, $service->listing()['items']);
        self::assertSame($other, $service->listing()['items'][0]['id']);
        self::assertSame('lamp', $service->detail($root)['nodes'][0]['children'][0]['children'][0]['title']);
        self::assertNull($service->detail($handler));
    }

    public function testLongChainIsNotCutByTheOldJournalLimit(): void
    {
        [$app] = $this->boot();
        $events = $app->container()->make(PropertyEventRepository::class);
        $root = $events->record(['type' => 'property.changed', 'status' => 'changed']);
        for ($index = 0; $index < 510; $index++) {
            $events->record(['parent_id' => $root, 'type' => 'module.delivery', 'status' => 'ok', 'handler' => 'subscriber-' . $index]);
        }
        $service = $app->container()->make(EventChainService::class);
        self::assertSame(511, $service->listing()['items'][0]['count']);
        $detail = $service->detail($root);
        self::assertNotNull($detail);
        self::assertCount(510, $detail['nodes'][0]['children']);
        self::assertSame('subscriber-509', $detail['nodes'][0]['children'][509]['title']);
    }

    public function testDeepChainRendersBeyondTwentyFourLevels(): void
    {
        [$app] = $this->boot();
        $events = $app->container()->make(PropertyEventRepository::class);
        $root = $events->record(['type' => 'property.changed', 'status' => 'changed']);
        $parent = $root;
        for ($depth = 1; $depth <= 60; $depth++) {
            $parent = $events->record(['parent_id' => $parent, 'type' => 'handler.method', 'status' => 'ok', 'object_title' => 'Depth', 'handler' => 'level' . $depth]);
        }
        $response = $app->handle(Request::create('/events?chain=' . $root, 'GET'));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Depth.level60', (string)$response->getContent());
    }

    public function testPendingDescendantPreventsCompletedStatus(): void
    {
        [$app] = $this->boot();
        $events = $app->container()->make(PropertyEventRepository::class);
        $root = $events->record(['type' => 'property.changed', 'status' => 'changed']);
        $execution = $app->container()->make(PropertyExecutionRepository::class);
        $step = $execution->create(0, 'property:1:1');
        $execution->update($step, ['event_id' => $root, 'status' => 'ok']);
        $child = $execution->create($step, 'module:lamp');
        $service = $app->container()->make(EventChainService::class);
        self::assertSame('running', $service->detail($root)['chain']['status']);
        self::assertSame(1, $service->detail($root)['chain']['pending']);
        $execution->update($child, ['status' => 'ok']);
        self::assertSame('ok', $service->detail($root)['chain']['status']);
        $execution->fail($child, 'delivery failed');
        self::assertSame('error', $service->detail($root)['chain']['status']);
    }

    public function testPaginationAndMissingAncestorsAreExplicit(): void
    {
        [$app] = $this->boot();
        $events = $app->container()->make(PropertyEventRepository::class);
        $first = $events->record(['type' => 'property.changed', 'status' => 'changed']);
        for ($index = 0; $index < 25; $index++) {
            $events->record(['type' => 'property.changed', 'status' => 'changed']);
        }
        $service = $app->container()->make(EventChainService::class);
        self::assertCount(25, $service->listing()['items']);
        self::assertTrue($service->listing()['more']);
        self::assertSame($first, $service->listing(page: 2)['items'][0]['id']);
        self::assertFalse($service->listing(page: 2)['more']);

        $orphan = $events->record(['parent_id' => 99999, 'type' => 'handler.method', 'status' => 'ok', 'handler' => 'survived']);
        self::assertTrue($service->detail($orphan)['chain']['partial']);
        $response = $app->handle(Request::create('/events?chain=' . $orphan, 'GET'));
        self::assertStringContainsString('Начало этой цепочки не сохранилось', (string)$response->getContent());
        self::assertNull($service->detail(99998));
    }

    public function testYoyoOpensTreeAndReturnsToFilteredList(): void
    {
        [$app] = $this->boot();
        self::assertTrue(sg('Кухня.temperature', '23.5'));
        $this->drainEvents($app);
        $id = (int)$this->events($app)[0]['id'];
        $update = static function (string $action, int $selectedId, array $args) use ($app): string {
            $_REQUEST = ['component' => 'events-table/' . $action, 'yoyo-id' => 'test-chain', 'selectedId' => $selectedId,
                'search' => 'temperature', 'type' => '', 'status' => '', 'page' => 1, 'actionArgs' => $args];
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['HTTP_HX_REQUEST'] = 'true';
            Yoyo::request()->mock($_REQUEST, $_SERVER);
            try {
                $response = $app->handle(Request::create('/yoyo', 'GET', $_REQUEST));
                self::assertSame(200, $response->getStatusCode());
                return (string)$response->getContent();
            } finally {
                Yoyo::request()->reset();
                unset($_SERVER['HTTP_HX_REQUEST'], $_SERVER['REQUEST_METHOD']);
                $_REQUEST = [];
            }
        };
        $detail = $update('open', 0, [$id]);
        self::assertStringContainsString('Кухня.onTemperature', $detail);
        self::assertStringContainsString('К списку цепочек', $detail);
        self::assertStringContainsString('name="search" value="temperature"', $detail);
        $list = $update('back', $id, []);
        self::assertStringContainsString('Цепочек на странице: 1', $list);
        self::assertStringNotContainsString('md-call-flow__tree', $list);
    }

    /**
     * @return array{0:Application,1:ArraySession}
     */
    private function boot(): array
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);

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
                $table->integer('class_id');
                $table->integer('object_id')->default(0);
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('data_type')->default('string');
                $table->boolean('keep_history')->default(false);
                $table->string('on_change')->default('');
                $table->timestamps();
                $table->softDeletes();
            },
            'property_values' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('object_id');
                $table->integer('property_id');
                $table->text('value')->nullable();
                $table->timestamps();
                $table->softDeletes();
            },
            'property_history' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('object_id');
                $table->integer('property_id');
                $table->text('value')->nullable();
                $table->timestamps();
                $table->softDeletes();
            },
            'methods' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('class_id');
                $table->integer('object_id')->default(0);
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('code')->nullable();
                $table->timestamps();
                $table->softDeletes();
            },
            'property_events' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedBigInteger('parent_id')->default(0);
                $table->string('type', 32)->default('');
                $table->string('status', 32)->default('');
                $table->string('source', 191)->default('');
                $table->integer('object_id')->default(0);
                $table->integer('property_id')->default(0);
                $table->string('object_title', 191)->default('');
                $table->string('property_title', 191)->default('');
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->string('handler', 191)->default('');
                $table->text('message')->nullable();
                $table->text('chain')->nullable();
                $table->integer('depth')->default(0);
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
            ['id' => 1, 'title' => 'Датчик', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'title' => 'Свет', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('objects')->insert([
            ['id' => 1, 'title' => 'Кухня', 'class_id' => 1, 'location_id' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'title' => 'Лампа', 'class_id' => 2, 'location_id' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'title' => 'Глобальный', 'class_id' => 0, 'location_id' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('properties')->insert([
            ['id' => 1, 'class_id' => 1, 'object_id' => 0, 'title' => 'temperature', 'data_type' => 'float', 'keep_history' => 1, 'on_change' => 'onTemperature', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'class_id' => 1, 'object_id' => 0, 'title' => 'alarm', 'data_type' => 'bool', 'keep_history' => 0, 'on_change' => 'onAlarm', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'class_id' => 1, 'object_id' => 0, 'title' => 'broken', 'data_type' => 'string', 'keep_history' => 0, 'on_change' => 'onBroken', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'class_id' => 1, 'object_id' => 0, 'title' => 'ghost', 'data_type' => 'string', 'keep_history' => 0, 'on_change' => 'missingMethod', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'class_id' => 2, 'object_id' => 0, 'title' => 'temperature', 'data_type' => 'float', 'keep_history' => 0, 'on_change' => '', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'class_id' => 0, 'object_id' => 3, 'title' => 'global', 'data_type' => 'string', 'keep_history' => 0, 'on_change' => '', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('methods')->insert([
            ['id' => 1, 'class_id' => 1, 'title' => 'onTemperature', 'description' => '', 'code' => 'if (($params["NEW_VALUE"] ?? "") === "hot") { sg("Кухня.alarm", "1"); } return true;', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'class_id' => 1, 'title' => 'onAlarm', 'description' => '', 'code' => 'sg("Кухня.temperature", "hot"); return true;', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'class_id' => 1, 'title' => 'onBroken', 'description' => '', 'code' => 'throw new RuntimeException("boom");', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function rowCount(Application $app, string $table): int
    {
        return $app->database()->connection()->table($table)->count();
    }

    private function rowsWhereCount(Application $app, string $table, string $column, string $value): int
    {
        return $app->database()->connection()->table($table)->where($column, $value)->count();
    }

    private function firstRowWhere(Application $app, string $table, string $column, string $value): ?object
    {
        return $app->database()->connection()->table($table)->where($column, $value)->orderBy('id')->first();
    }

    /**
     * @return list<array{id:int,parent_id:int,type:string,status:string,depth:int,old_value:string,new_value:string,handler:string,chain:list<string>}>
     */
    private function events(Application $app): array
    {
        return $app->database()->connection()->table('property_events')
            ->orderBy('id')
            ->get()
            ->map(static function (object $row): array {
                $decoded = json_decode((string)($row->chain ?? '[]'), true);
                $chain = is_array($decoded)
                    ? array_values(array_map(static fn (mixed $token): string => (string)$token, $decoded))
                    : [];

                return [
                    'id' => (int)$row->id,
                    'parent_id' => (int)$row->parent_id,
                    'type' => (string)$row->type,
                    'status' => (string)$row->status,
                    'depth' => (int)$row->depth,
                    'old_value' => (string)($row->old_value ?? ''),
                    'new_value' => (string)($row->new_value ?? ''),
                    'handler' => (string)$row->handler,
                    'chain' => $chain,
                ];
            })
            ->all();
    }
}
