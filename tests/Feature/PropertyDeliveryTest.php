<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use MajorDoMo\Events\PropertyChange;
use MajorDoMo\Events\PropertyEventQueue;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Modules\PropertySubscriber;
use MajorDoMo\Modules\PropertySubscriberRegistry;
use MajorDoMo\Properties\PropertySubscriptionRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Доставка изменений свойства подписанным модулям.
 *
 * Подписку выбирает пользователь, поэтому проверяется не манифест, а связка
 * «строка подписки + зарегистрированный обработчик». Обработчик тестовый:
 * реальный каталог модуля не нужен.
 *
 * 2026-10-27 — ошибка отменяет оставшиеся привязки одной цепочки.
 * 2026-10-27 — тест создан вместе с доставкой `module.delivery`.
 */
final class PropertyDeliveryTest extends TestCase
{
    use DrainsEvents;

    public function testSubscribedModuleReceivesChangeAndJournalStep(): void
    {
        [$app, $subscriber] = $this->boot();

        $this->subscribe($app, 1, 1, 'demo');
        self::assertTrue(sg('Кухня.temperature', '23.5'));
        $this->drainEvents($app);

        self::assertCount(1, $subscriber->changes);
        $change = $subscriber->changes[0];
        self::assertSame(1, $change->objectId);
        self::assertSame(1, $change->propertyId);
        self::assertSame('Кухня', $change->objectTitle);
        self::assertSame('temperature', $change->propertyTitle);
        self::assertSame('', $change->oldValue);
        self::assertSame('23.5', $change->newValue);
        self::assertSame('sg', $change->source);
        self::assertTrue($change->changed);
        self::assertSame(['Кухня.temperature'], $change->chain);

        $delivery = $this->firstRow($app, 'module.delivery');
        self::assertNotNull($delivery);
        self::assertSame('ok', $delivery->status);
        self::assertSame('demo', $delivery->handler);
        self::assertSame('module.delivery', $delivery->type);
        self::assertNotNull($this->firstRow($app, 'property.changed'));
        self::assertSame(
            (int)$this->firstRow($app, 'property.changed')->id,
            (int)$delivery->parent_id
        );
    }

    public function testSubscriberErrorIsJournaledAndValueIsSaved(): void
    {
        [$app, $subscriber] = $this->boot();
        $subscriber->fail = true;

        $this->subscribe($app, 1, 1, 'demo');
        self::assertTrue(sg('Кухня.temperature', '7'));
        $this->drainEvents($app);

        self::assertSame('7', gg('Кухня.temperature'));
        $delivery = $this->firstRow($app, 'module.delivery');
        self::assertNotNull($delivery);
        self::assertSame('error', $delivery->status);
        self::assertStringContainsString('subscriber down', (string)$delivery->message);

        // Ошибка подписчика не мешает OnChange-методу свойства.
        $handler = $this->firstRow($app, 'handler.method');
        self::assertNotNull($handler);
        self::assertSame('ok', $handler->status);
    }

    public function testMethodWriteToAnotherPropertyReachesSubscriber(): void
    {
        [$app, $subscriber] = $this->boot();
        $this->subscribe($app, 2, 2, 'demo');

        // Пользовательский метод свойства пишет команду лампе — это другое
        // свойство, оно должно уйти подписчику как обычное изменение.
        self::assertTrue(sg('Кухня.trigger', '1'));
        $this->drainEvents($app);

        self::assertSame('on', gg('Лампа.state'));
        self::assertCount(1, $subscriber->changes);
        $change = $subscriber->changes[0];
        self::assertSame('Лампа.state', $change->token());
        self::assertSame(['Кухня.trigger', 'Лампа.state'], $change->chain);
    }

    public function testSubscriberDoesNotReceiveEchoOfItsOwnWrite(): void
    {
        [$app, $subscriber] = $this->boot();
        $this->subscribe($app, 1, 1, 'demo');
        $this->subscribe($app, 2, 2, 'demo');

        $subscriber->onChange = static function (PropertyChange $change): void {
            if ($change->token() === 'Кухня.temperature') {
                sg('Лампа.state', 'on');
            }
        };

        self::assertTrue(sg('Кухня.temperature', '23'));
        $this->drainEvents($app);

        // Значение записано, но своё же изменение модуль обратно не получает:
        // иначе MQTT-модуль публиковал бы собственный эхо-ответ.
        self::assertSame('on', gg('Лампа.state'));
        self::assertCount(1, $subscriber->changes);
        self::assertSame('Кухня.temperature', $subscriber->changes[0]->token());
        self::assertSame(1, $app->database()->connection()->table('property_events')
            ->where('type', 'module.delivery')
            ->count());
    }

    public function testAnotherSubscriberReceivesPropertyWrittenByFirstModule(): void
    {
        [$app, $subscriber] = $this->boot();
        $other = new TestPropertySubscriber();
        $app->container()->make(PropertySubscriberRegistry::class)
            ->register('other', static fn (): PropertySubscriber => $other);

        $this->subscribe($app, 1, 1, 'demo');
        $this->subscribe($app, 2, 2, 'other');
        $subscriber->onChange = static function (PropertyChange $change): void {
            if ($change->token() === 'Кухня.temperature') {
                sg('Лампа.state', 'on');
            }
        };

        self::assertTrue(sg('Кухня.temperature', '23'));
        $this->drainEvents($app);

        // Эхо гасится только для автора записи; остальные подписчики
        // получают изменение как обычно.
        self::assertCount(1, $subscriber->changes);
        self::assertCount(1, $other->changes);
        self::assertSame('Лампа.state', $other->changes[0]->token());
    }

    public function testRepeatedSamePropertyStopsOnChainButSavesValue(): void
    {
        [$app, $subscriber] = $this->boot();
        $subscriber->onChange = static function (PropertyChange $change): void {
            sg($change->objectTitle . '.' . $change->propertyTitle, '99');
        };

        $this->subscribe($app, 1, 1, 'demo');
        self::assertTrue(sg('Кухня.temperature', '22'));
        $this->drainEvents($app);

        // Запись того же самого свойства внутри обработчика — цикл: значение
        // сохраняется, но второй обработки и доставки нет.
        self::assertCount(1, $subscriber->changes);
        self::assertSame('99', gg('Кухня.temperature'));
        self::assertSame(1, $app->database()->connection()->table('property_events')
            ->where('status', 'stopped')
            ->count());
    }

    public function testHistoryIsSavedBeforeUserCodeAndDelivery(): void
    {
        [$app, $subscriber] = $this->boot();
        $this->subscribe($app, 1, 4, 'demo');

        // Оба обработчика уже видят историю принятого значения.
        $GLOBALS['md_probe'] = static fn (): int => $app->database()->connection()
            ->table('property_history')
            ->count();
        $GLOBALS['md_order'] = [];
        $subscriber->onChange = static function (PropertyChange $change): void {
            $GLOBALS['md_order'][] = ['module', ($GLOBALS['md_probe'])()];
        };

        try {
            self::assertTrue(sg('Кухня.order', '42'));
            $this->drainEvents($app);
            self::assertSame([
                ['handler', 1],
                ['module', 1],
            ], $GLOBALS['md_order']);
        } finally {
            unset($GLOBALS['md_order'], $GLOBALS['md_probe']);
        }
    }

    public function testOnChangeFailureStopsDeliveryButKeepsHistoryAndLaterCommands(): void
    {
        [$app, $subscriber] = $this->boot();
        $this->subscribe($app, 1, 1, 'demo');
        $app->database()->connection()->table('methods')->where('title', 'onTemperature')->update([
            'code' => 'if ($params["NEW_VALUE"] === "bad") { throw new \\RuntimeException("boom"); }',
        ]);

        self::assertTrue(sg('Кухня.temperature', 'bad'));
        self::assertTrue(sg('Кухня.temperature', 'good'));
        $this->drainEvents($app);

        self::assertSame('good', gg('Кухня.temperature'));
        self::assertSame(2, $app->database()->connection()->table('property_history')->count());
        self::assertCount(1, $subscriber->changes);
        self::assertSame('good', $subscriber->changes[0]->newValue);
        self::assertSame(1, $app->database()->connection()->table('property_events')
            ->where('type', 'handler.method')->where('status', 'error')->count());
    }

    public function testDeliveryIsQueuedForModuleWorker(): void
    {
        [$app, $subscriber] = $this->boot();
        $this->subscribe($app, 1, 1, 'demo');

        self::assertTrue(sg('Кухня.temperature', '23'));

        // Событие обработано events-процессом, но код модуля ещё не вызван:
        // доставка лежит в очереди module.demo.
        $queue = $app->queue();
        $jobs = $app->jobs();
        $events = $app->container()->make(PropertyEventQueue::class);
        $handled = false;
        foreach ($events->queueNames() as $name) {
            while (($job = $queue->pop($name)) !== null) {
                $jobs->handle($job->job, $job->payload);
                $queue->ack($job);
                $handled = true;
            }
        }
        self::assertTrue($handled);
        self::assertCount(0, $subscriber->changes);
        self::assertSame(1, $queue->stats('module.demo')['pending']);

        $app->container()->make(\MajorDoMo\Worker\CoreWorker::class)->run('module.demo', true);
        self::assertCount(1, $subscriber->changes);
        self::assertSame('Кухня.temperature', $subscriber->changes[0]->token());
    }

    public function testFailedMethodCancelsNestedCommandsAndKeepsTheirHistory(): void
    {
        [$app, $subscriber] = $this->boot();
        $this->subscribe($app, 2, 2, 'demo');
        $connection = $app->database()->connection();
        $connection->table('properties')->where('id', 2)->update(['keep_history' => true]);
        $connection->table('methods')->where('title', 'onTrigger')->update([
            'code' => 'sg("Лампа.state", "on"); throw new \\RuntimeException("parent failed");',
        ]);

        self::assertTrue(sg('Кухня.trigger', '1'));
        $this->drainEvents($app);

        self::assertSame('on', gg('Лампа.state'));
        self::assertCount(0, $subscriber->changes);
        self::assertSame(1, $connection->table('property_history')->where('property_id', 2)->count());
        self::assertSame(1, $connection->table('property_events')->where('property_title', 'state')
            ->where('status', 'skipped')->count());
        $error = $connection->table('system_errors')->first();
        self::assertNotNull($error);
        self::assertStringContainsString('parent failed', (string)$error->message);
        $errorContext = json_decode((string)$error->context, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($errorContext);
        self::assertGreaterThan(0, $errorContext['execution_id']);
        self::assertGreaterThan(0, $errorContext['event_id']);

        self::assertTrue(sg('Лампа.state', 'off'));
        $this->drainEvents($app);
        self::assertCount(1, $subscriber->changes);
        self::assertSame('off', $subscriber->changes[0]->newValue);
        self::assertSame(2, $connection->table('property_history')->where('property_id', 2)->count());
    }

    public function testFailedSubscriberCancelsItsNestedCommands(): void
    {
        [$app, $first] = $this->boot();
        $second = new TestPropertySubscriber();
        $app->container()->make(PropertySubscriberRegistry::class)
            ->register('other', static fn (): PropertySubscriber => $second);
        $this->subscribe($app, 1, 1, 'demo');
        $this->subscribe($app, 2, 2, 'other');
        $first->onChange = static function (): void {
            sg('Лампа.state', 'on');
            throw new RuntimeException('delivery failed');
        };

        self::assertTrue(sg('Кухня.temperature', '7'));
        $this->drainEvents($app);
        self::assertSame('on', gg('Лампа.state'));
        self::assertCount(0, $second->changes);

        self::assertTrue(sg('Лампа.state', 'off'));
        $this->drainEvents($app);
        self::assertCount(1, $second->changes);
    }

    public function testTimedOutMethodCancelsChildrenAndNextCommandStillRuns(): void
    {
        if (!(new \MajorDoMo\Runtime\UserCodeDeadline(1))->supported()) {
            self::markTestSkipped('Таймаут обработчика требует Linux/pcntl.');
        }
        [$app, $subscriber] = $this->boot(['handler_timeout' => 1]);
        $this->subscribe($app, 1, 1, 'demo');
        $connection = $app->database()->connection();
        $connection->table('methods')->where('title', 'onTemperature')->update([
            'code' => 'sg("Лампа.state", "on"); sleep(3);',
        ]);
        self::assertTrue(sg('Кухня.temperature', 'slow'));
        $this->drainEvents($app);
        self::assertCount(0, $subscriber->changes);
        self::assertSame(1, $connection->table('property_history')->count());
        self::assertSame(1, $connection->table('property_events')->where('status', 'error')->count());
        self::assertSame(1, $connection->table('property_events')->where('status', 'skipped')->count());

        $connection->table('methods')->where('title', 'onTemperature')->update(['code' => 'return true;']);
        self::assertTrue(sg('Кухня.temperature', 'healthy'));
        $this->drainEvents($app);
        self::assertCount(1, $subscriber->changes);
        self::assertSame('healthy', $subscriber->changes[0]->newValue);
    }

    public function testRetryAfterDeliveryFailureDoesNotRepeatSuccessfulMethod(): void
    {
        [$app, $subscriber] = $this->boot();
        $this->subscribe($app, 1, 1, 'demo');
        $connection = $app->database()->connection();
        $connection->table('methods')->where('title', 'onTemperature')->update([
            'code' => '$GLOBALS["md_method_runs"]++;',
        ]);
        $GLOBALS['md_method_runs'] = 0;
        $queue = $app->queue();
        $lanes = $app->container()->make(PropertyEventQueue::class);
        self::assertTrue(sg('Кухня.temperature', '42'));
        $job = $queue->pop($lanes->queueName($lanes->laneFor(1, 1)));
        self::assertNotNull($job);
        $connection->getSchemaBuilder()->rename('property_subscriptions', 'saved_subscriptions');
        try {
            try {
                $app->jobs()->handle($job->job, $job->payload);
                self::fail('Постановка доставки должна завершиться ошибкой отсутствующей таблицы.');
            } catch (\Illuminate\Database\QueryException) {
                // Следующая попытка должна продолжиться с этапа доставки.
            } finally {
                $connection->getSchemaBuilder()->rename('saved_subscriptions', 'property_subscriptions');
            }

            $app->jobs()->handle($job->job, $job->payload);
            $app->jobs()->handle($job->job, $job->payload);
            $queue->ack($job);
            $this->drainEvents($app);
            self::assertSame(1, $GLOBALS['md_method_runs']);
            self::assertSame(1, $connection->table('property_history')->count());
            self::assertCount(1, $subscriber->changes);
        } finally {
            unset($GLOBALS['md_method_runs']);
        }
    }

    public function testFailingModuleCancelsRemainingDeliveryInSameChain(): void
    {
        [$app, $first] = $this->boot();
        $second = new TestPropertySubscriber();
        $app->container()->make(PropertySubscriberRegistry::class)
            ->register('other', static fn (): PropertySubscriber => $second);
        $first->fail = true;

        $this->subscribe($app, 1, 1, 'demo');
        $this->subscribe($app, 1, 1, 'other');
        self::assertTrue(sg('Кухня.temperature', '23'));
        $this->drainEvents($app);

        self::assertCount(0, $first->changes);
        self::assertCount(0, $second->changes);
        $statuses = $app->database()->connection()->table('property_events')
            ->where('type', 'module.delivery')
            ->orderBy('id')
            ->pluck('status')
            ->map(static fn (mixed $status): string => (string)$status)
            ->all();
        self::assertSame(['error', 'skipped'], $statuses);
    }

    public function testSubscriptionRepositoryIsIdempotent(): void
    {
        [$app] = $this->boot();
        $repository = $app->container()->make(PropertySubscriptionRepository::class);

        self::assertTrue($repository->attach(1, 1, 'demo'));
        self::assertTrue($repository->attach(1, 1, 'demo'));
        self::assertSame(['demo'], $repository->modulesFor(1, 1));

        self::assertTrue($repository->detach(1, 1, 'demo'));
        self::assertFalse($repository->detach(1, 1, 'demo'));
        self::assertSame([], $repository->modulesFor(1, 1));
    }

    public function testUnregisteredSubscriberIsReportedAsMissing(): void
    {
        [$app] = $this->boot();

        $this->subscribe($app, 1, 1, 'ghost');
        self::assertTrue(sg('Кухня.temperature', '5'));
        $this->drainEvents($app);

        $delivery = $this->firstRow($app, 'module.delivery');
        self::assertNotNull($delivery);
        self::assertSame('missing', $delivery->status);
        self::assertSame('ghost', $delivery->handler);
    }

    private function subscribe(Application $app, int $objectId, int $propertyId, string $module): void
    {
        $repository = $app->container()->make(PropertySubscriptionRepository::class);
        self::assertTrue($repository->attach($objectId, $propertyId, $module));
    }

    /**
     * @param array<string, mixed> $queueConfig
     * @return array{0:Application,1:TestPropertySubscriber}
     */
    private function boot(array $queueConfig = []): array
    {
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => new \MajorDoMo\Http\Session\ArraySession(),
            'cache' => ['driver' => 'array'],
            'queue' => $queueConfig,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();

        foreach ($this->schema() as $name => $definition) {
            $app->database()->schema()->create($name, $definition);
        }
        $this->seed($app);

        $subscriber = new TestPropertySubscriber();
        $app->container()->make(PropertySubscriberRegistry::class)
            ->register('demo', static fn (): PropertySubscriber => $subscriber);

        return [$app, $subscriber];
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
            'property_subscriptions' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('object_id');
                $table->integer('property_id');
                $table->string('module', 64);
                $table->timestamps();
                $table->unique(['object_id', 'property_id', 'module']);
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
            'system_errors' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('level', 16);
                $table->text('message');
                $table->text('context')->nullable();
                $table->string('request_id', 64)->default('');
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
        ]);
        $connection->table('objects')->insert([
            ['id' => 1, 'title' => 'Кухня', 'class_id' => 1, 'location_id' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'title' => 'Лампа', 'class_id' => 1, 'location_id' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('properties')->insert([
            ['id' => 1, 'class_id' => 1, 'object_id' => 0, 'title' => 'temperature', 'data_type' => 'float', 'keep_history' => 1, 'on_change' => 'onTemperature', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'class_id' => 1, 'object_id' => 0, 'title' => 'state', 'data_type' => 'string', 'keep_history' => 0, 'on_change' => '', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'class_id' => 1, 'object_id' => 0, 'title' => 'trigger', 'data_type' => 'bool', 'keep_history' => 0, 'on_change' => 'onTrigger', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'class_id' => 1, 'object_id' => 0, 'title' => 'order', 'data_type' => 'int', 'keep_history' => 1, 'on_change' => 'onOrder', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('methods')->insert([
            ['id' => 1, 'class_id' => 1, 'title' => 'onTemperature', 'description' => '', 'code' => 'return true;', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'class_id' => 1, 'title' => 'onTrigger', 'description' => '', 'code' => 'sg("Лампа.state", "on"); return true;', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'class_id' => 1, 'title' => 'onOrder', 'description' => '', 'code' => '$GLOBALS["md_order"][] = ["handler", ($GLOBALS["md_probe"])()]; return true;', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function firstRow(Application $app, string $type): ?object
    {
        return $app->database()->connection()->table('property_events')
            ->where('type', $type)
            ->orderBy('id')
            ->first();
    }
}

/**
 * Тестовый обработчик: собирает изменения и умеет падать или писать обратно.
 */
final class TestPropertySubscriber implements PropertySubscriber
{
    /** @var list<PropertyChange> */
    public array $changes = [];

    public bool $fail = false;

    /** @var (callable(PropertyChange):void)|null */
    public $onChange = null;

    public function onPropertyChanged(PropertyChange $change): void
    {
        if ($this->fail) {
            throw new RuntimeException('subscriber down');
        }
        if ($this->onChange !== null) {
            ($this->onChange)($change);
        }
        $this->changes[] = $change;
    }
}
