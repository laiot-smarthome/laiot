<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Contracts\Translation\Translator as TranslatorContract;
use Illuminate\Contracts\Validation\Factory as FactoryContract;
use Illuminate\Database\DatabaseManager as IlluminateDatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * Интеграция штатной валидации: DI, русский язык, вложенные данные и общая БД.
 *
 * Изменения:
 * 2026-10-28 — проверки без рабочего bootstrap и внешних сервисов.
 */
final class ValidationTest extends TestCase
{
    public function testSharedFactoryValidatesNestedInputWithoutOpeningDatabase(): void
    {
        $app = $this->application(false);
        $container = $app->container();
        $factory = $container->make(FactoryContract::class);
        self::assertSame($factory, $container->make(Factory::class));
        self::assertSame($factory, $container->make('validator'));
        self::assertSame($container->make(Translator::class), $container->make(TranslatorContract::class));
        self::assertSame($container->make(Translator::class), $container->make('translator'));

        $validated = $factory->make([
            'devices' => [['name' => 'Датчик', 'internal' => true]],
            'admin' => true,
        ], [
            'devices' => ['required', 'array'],
            'devices.*.name' => ['required', 'string', 'max:100'],
        ])->validate();
        self::assertSame(['devices' => [['name' => 'Датчик']]], $validated);
        $resolver = $app->database()->resolver();
        self::assertInstanceOf(IlluminateDatabaseManager::class, $resolver);
        self::assertSame([], $resolver->getConnections());
    }

    public function testErrorsUseRussianMessagesAndFieldNames(): void
    {
        $factory = $this->application(false)->container()->make(FactoryContract::class);
        $validator = $factory->make(['name' => 'Длинное имя', 'email' => 'bad'], [
            'name' => ['required', 'string', 'max:3'],
            'email' => ['required', 'email'],
        ], [], ['name' => 'Название', 'email' => 'Почта']);

        try {
            $validator->validate();
            self::fail('Неверные поля должны отклоняться валидатором.');
        } catch (ValidationException $error) {
            self::assertSame(422, $error->status);
            self::assertSame(['name', 'email'], array_keys($error->errors()));
            self::assertStringContainsString('Название', $validator->errors()->first('name'));
            self::assertStringContainsString('3', $validator->errors()->first('name'));
            self::assertStringContainsString('Почта', $validator->errors()->first('email'));
            self::assertStringNotContainsString('validation.', $error->getMessage());
            self::assertStringNotContainsString('and ', $error->getMessage());
            self::assertStringContainsString('ещё 1 ошибка', $error->getMessage());
        }
    }

    public function testConditionalRulesAndCustomMessagesRemainAvailable(): void
    {
        $factory = $this->application(false)->container()->make(FactoryContract::class);
        $validator = $factory->make(['mode' => 'mqtt'], [
            'mode' => ['required', Rule::in(['mqtt', 'local'])],
            'host' => ['required_if:mode,mqtt', 'nullable', 'string'],
        ], ['host.required_if' => 'Укажите MQTT-брокер.']);
        self::assertTrue($validator->fails());
        self::assertSame(['host' => ['Укажите MQTT-брокер.']], $validator->errors()->toArray());
        self::assertSame(['mode' => 'local'], $factory->make(['mode' => 'local'], [
            'mode' => ['required', Rule::in(['mqtt', 'local'])],
            'host' => ['required_if:mode,mqtt', 'nullable', 'string'],
        ])->validate());
    }

    public function testUniqueAndExistsSeeTheApplicationTransaction(): void
    {
        $app = $this->application();
        $app->database()->schema()->create('validation_devices', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->unique();
        });
        $factory = $app->container()->make(FactoryContract::class);
        $connection = $app->database()->connection();
        $connection->beginTransaction();
        try {
            $id = $connection->table('validation_devices')->insertGetId(['name' => 'sensor']);
            self::assertTrue($factory->make(['name' => 'sensor'], [
                'name' => ['required', 'unique:validation_devices,name'],
            ])->fails());
            self::assertFalse($factory->make(['id' => $id], [
                'id' => ['required', 'exists:validation_devices,id'],
            ])->fails());
            self::assertTrue($factory->make(['id' => $id + 1], [
                'id' => ['required', 'exists:validation_devices,id'],
            ])->fails());
            self::assertFalse($factory->make(['name' => 'sensor'], [
                'name' => ['required', Rule::unique('validation_devices', 'name')->ignore($id)],
            ])->fails());
        } finally {
            $connection->rollBack();
        }
        self::assertFalse($factory->make(['name' => 'sensor'], [
            'name' => ['required', 'unique:validation_devices,name'],
        ])->fails());
    }

    private function application(bool $database = true): Application
    {
        return (new Application(dirname(__DIR__, 2), [
            'session' => new ArraySession(),
            'cache' => ['driver' => 'array'],
            'database' => [
                'driver' => $database ? 'sqlite' : '',
                'database' => $database ? ':memory:' : '',
                'prefix' => '',
            ],
            'log' => 'php://stderr',
        ]))->boot();
    }
}
