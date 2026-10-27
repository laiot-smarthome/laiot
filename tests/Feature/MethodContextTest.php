<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Methods\MethodContext;
use PHPUnit\Framework\TestCase;

/**
 * `$this` внутри метода: контекст объекта, запись, вложенный вызов и история.
 *
 * 2026-10-27 — тест создан вместе с MethodContext.
 */
final class MethodContextTest extends TestCase
{
    use DrainsEvents;

    public function testMethodReceivesObjectContextAndWritesProperty(): void
    {
        $app = $this->boot();

        $result = callMethod('Кухня', 'compose', ['x' => 7]);

        self::assertSame(['Кухня', 'compose', '7', 7], $result);
        self::assertSame('7', gg('Кухня.state'));
        self::assertSame(1, $this->pendingEvents($app));
    }

    public function testMethodCanCallAnotherMethodOfSameObject(): void
    {
        $this->boot();

        self::assertSame(6, callMethod('Кухня', 'outer', ['x' => 3]));
    }

    public function testHistoryInsideMethodAndAfterEventsWorker(): void
    {
        $app = $this->boot();

        // До обработки очереди история ещё пуста: запись асинхронна.
        self::assertSame(0, callMethod('Кухня', 'historyCount'));
        callMethod('Кухня', 'compose', ['x' => 5]);
        $this->drainEvents($app);

        self::assertSame(1, callMethod('Кухня', 'historyCount'));
        self::assertSame(1, history('Кухня.state')?->count());
    }

    public function testThisIsMethodContextInstance(): void
    {
        $this->boot();

        self::assertTrue(callMethod('Кухня', 'kind'));
        self::assertSame('Кухня', callMethod('Кухня', 'title'));
    }

    private function boot(): Application
    {
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => new \MajorDoMo\Http\Session\ArraySession(),
            'cache' => ['driver' => 'array'],
            'queue' => ['events_lanes' => 1],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();

        foreach ($this->schema() as $name => $definition) {
            $app->database()->schema()->create($name, $definition);
        }
        $this->seed($app);

        return $app;
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
        ];
    }

    private function seed(Application $app): void
    {
        $connection = $app->database()->connection();
        $now = date('Y-m-d H:i:s');

        $connection->table('classes')->insert([
            ['id' => 1, 'title' => 'Датчик', 'parent_id' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('objects')->insert([
            ['id' => 1, 'title' => 'Кухня', 'class_id' => 1, 'location_id' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('properties')->insert([
            ['id' => 1, 'class_id' => 1, 'object_id' => 0, 'title' => 'state', 'data_type' => 'string', 'keep_history' => 1, 'on_change' => '', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('methods')->insert([
            [
                'id' => 1,
                'class_id' => 1,
                'title' => 'compose',
                'description' => '',
                'code' => '$this->set("state", $this->param("x"));'
                    . ' return [$this->title(), $this->method(), $this->get("state"), $this->param("x")];',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'class_id' => 1,
                'title' => 'inner',
                'description' => '',
                'code' => 'return (int)$this->param("x") * 2;',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'class_id' => 1,
                'title' => 'outer',
                'description' => '',
                'code' => 'return $this->call("inner", ["x" => 3]);',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'class_id' => 1,
                'title' => 'historyCount',
                'description' => '',
                'code' => 'return $this->history("state")?->count() ?? -1;',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'class_id' => 1,
                'title' => 'kind',
                'description' => '',
                'code' => 'return $this instanceof ' . MethodContext::class . ';',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 6,
                'class_id' => 1,
                'title' => 'title',
                'description' => '',
                'code' => 'return $this->title();',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
