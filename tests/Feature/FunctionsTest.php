<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * Alpha-совместимые функции пользовательских сценариев: методы и скрипты.
 */
final class FunctionsTest extends TestCase
{
    public function testMethodAliasesAndBothForms(): void
    {
        $app = $this->boot();

        self::assertSame(10, callMethod('Кухня', 'double', ['x' => 5]));
        self::assertSame(10, callMethod('Кухня.double', ['x' => 5]));
        self::assertSame(10, cm('Кухня.double', ['x' => 5]));
        self::assertSame(10, runMethod('Кухня.double', ['x' => 5]));
        self::assertSame(10, callMethodSafe('Кухня.double', ['x' => 5]));
        self::assertNull(callMethod('Кухня'));
    }

    public function testGetGlobalAndSetGlobalAliases(): void
    {
        $app = $this->boot();

        self::assertTrue(setGlobal('Кухня.temperature', '23.5', 'test'));
        self::assertSame('23.5', getGlobal('Кухня.temperature'));
        self::assertSame('23.5', gg('Кухня.temperature'));
        self::assertFalse(setGlobal('Кухня.unknown', 'x'));
    }

    public function testScriptRunsByIdTitleAndAliases(): void
    {
        $app = $this->boot();

        self::assertSame(6, runScript(1, ['x' => 2]));
        self::assertSame(6, runScript('Triple', ['x' => 2]));
        self::assertSame(6, callScript(1, ['x' => 2]));
        self::assertSame(6, runScriptSafe(1, ['x' => 2]));
        self::assertSame(6, rs(1, ['x' => 2]));
    }

    public function testScriptCanUsePropertyHelpers(): void
    {
        $app = $this->boot();

        $result = runScript(2);

        self::assertSame('fromScript', $result);
        self::assertSame('fromScript', getGlobal('Кухня.temperature'));
    }

    public function testMissingScriptAndMethodAreReported(): void
    {
        $app = $this->boot();

        try {
            runScript(999);
            self::fail('Ожидалось исключение для отсутствующего сценария.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('999', $error->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        cm('Кухня.нетТакогоМетода');
    }

    private function boot(): Application
    {
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => new ArraySession(),
            'cache' => ['driver' => 'array'],
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
                $table->integer('class_id')->default(0);
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
            'scripts' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title');
                $table->text('code')->nullable();
                $table->text('description')->nullable();
                $table->integer('category_id')->default(0);
                $table->string('event_name', 191)->default('');
                $table->boolean('use_core')->default(true);
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
            'id' => 1, 'title' => 'Кухня', 'class_id' => 1, 'location_id' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $connection->table('properties')->insert([
            'id' => 1, 'class_id' => 1, 'title' => 'temperature', 'data_type' => 'string',
            'keep_history' => 0, 'on_change' => '', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $connection->table('methods')->insert([
            'id' => 1, 'class_id' => 1, 'title' => 'double',
            'code' => 'return ((int)($params["x"] ?? 0)) * 2;',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $connection->table('scripts')->insert([
            [
                'id' => 1, 'title' => 'Triple',
                'code' => 'return ((int)($params["x"] ?? 0)) * 3;',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => 2, 'title' => 'WriteProperty',
                'code' => 'sg("Кухня.temperature", "fromScript"); return getGlobal("Кухня.temperature");',
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }
}
