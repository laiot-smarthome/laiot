<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * Цепочка события свойства: запись → история → строка журнала → OnChange-метод.
 *
 * Проверяется на SQLite в памяти, без циклов, очередей и внешних сервисов.
 */
final class CoreEventsTest extends TestCase
{
    public function testChangeRunsOnChangeAndAppearsInJournal(): void
    {
        [$app] = $this->boot();

        self::assertTrue(sg('Кухня.temperature', '23.5'));

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

        $page = $app->handle(Request::create('/module/events', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        $html = (string)$page->getContent();
        self::assertStringContainsString('Кухня', $html);
        self::assertStringContainsString('onTemperature', $html);
        self::assertStringContainsString('Значение изменено', $html);
        // Форма фильтра передаёт option слотом — компонент x-md.select обязан
        // отрендерить их, а не только массив options.
        self::assertStringContainsString('<option value="handler.method"', $html);
    }

    public function testRepeatedValueRunsHandlerWithoutSecondHistoryRow(): void
    {
        $app = $this->boot()[0];

        self::assertTrue(sg('Кухня.temperature', '23.5'));
        self::assertTrue(sg('Кухня.temperature', '23.5'));

        self::assertSame(1, $this->rowCount($app, 'property_history'));
        self::assertSame(1, $this->rowsWhereCount($app, 'property_events', 'type', 'property.changed'));
        self::assertSame(2, $this->rowsWhereCount($app, 'property_events', 'type', 'handler.method'));
    }

    public function testLoopStopsAtRepeatedPropertyAndSavesValue(): void
    {
        $app = $this->boot()[0];

        self::assertTrue(sg('Кухня.alarm', '0'));

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

        $page = $app->handle(Request::create('/module/events', 'GET', ['status' => 'stopped']));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('Цикл остановлен', (string)$page->getContent());
    }

    public function testHandlerErrorDoesNotRollBackValue(): void
    {
        $app = $this->boot()[0];

        self::assertTrue(sg('Кухня.broken', 'x'));

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

        // У alarm история выключена; вложенная запись temperature её не включает
        // для самого alarm.
        self::assertSame(0, $app->database()->connection()->table('property_history')
            ->where('property_id', 2)
            ->count());
    }

    public function testPvaluesPageWritesThroughChain(): void
    {
        [$app, $session] = $this->boot();

        self::assertTrue(sg('Кухня.temperature', '20'));
        $value = $app->database()->connection()->table('property_values')->first();

        $response = $app->handle(Request::create('/module/pvalues/' . $value->id, 'POST', [
            '_token' => $session->csrfToken(),
            'value' => '21',
        ]));
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('21', gg('Кухня.temperature'));
        self::assertSame(2, $this->rowCount($app, 'property_history'));

        $event = $this->firstRowWhere($app, 'property_events', 'source', 'panel:pvalues');
        self::assertNotNull($event);
        self::assertSame('property.changed', $event->type);
        self::assertSame('21', $event->new_value);
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
            ['id' => 1, 'class_id' => 1, 'title' => 'temperature', 'data_type' => 'float', 'keep_history' => 1, 'on_change' => 'onTemperature', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'class_id' => 1, 'title' => 'alarm', 'data_type' => 'bool', 'keep_history' => 0, 'on_change' => 'onAlarm', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'class_id' => 1, 'title' => 'broken', 'data_type' => 'string', 'keep_history' => 0, 'on_change' => 'onBroken', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'class_id' => 1, 'title' => 'ghost', 'data_type' => 'string', 'keep_history' => 0, 'on_change' => 'missingMethod', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'class_id' => 2, 'title' => 'temperature', 'data_type' => 'float', 'keep_history' => 0, 'on_change' => '', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'class_id' => 0, 'title' => 'global', 'data_type' => 'string', 'keep_history' => 0, 'on_change' => '', 'created_at' => $now, 'updated_at' => $now],
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
