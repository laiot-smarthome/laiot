<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\Database\Migrator;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * Политика повторной записи свойства.
 *
 * 2026-10-27 — при включённом `only_new_values` повтор того же значения
 * принимается, но не создаёт событие, историю и доставку модулям. Выключенная
 * галочка сохраняет прежнее поведение: повтор запускает цепочку.
 */
final class PropertyRepeatPolicyTest extends TestCase
{
    use DrainsEvents;

    public function testOnlyNewValuesAcceptsRepeatWithoutStartingChain(): void
    {
        $app = $this->boot(1);

        self::assertTrue(sg('KitchenLight.state', 'on'));
        $this->drainEvents($app);
        self::assertSame(1, $GLOBALS['repeat_count'] ?? 0);
        self::assertSame(1, $app->database()->connection()->table('property_history')->count());

        self::assertTrue(sg('KitchenLight.state', 'on'));
        self::assertSame(0, $this->drainEvents($app));
        self::assertSame(1, $GLOBALS['repeat_count'] ?? 0);
        self::assertSame(1, $app->database()->connection()->table('property_history')->count());
        self::assertSame('on', gg('KitchenLight.state'));

        self::assertTrue(sg('KitchenLight.state', 'off'));
        $this->drainEvents($app);
        self::assertSame(2, $GLOBALS['repeat_count'] ?? 0);
        self::assertSame(2, $app->database()->connection()->table('property_history')->count());
    }

    public function testDefaultPolicyRepeatsChainOnSameValue(): void
    {
        $app = $this->boot(0);

        self::assertTrue(sg('KitchenLight.state', 'on'));
        $this->drainEvents($app);
        self::assertSame(1, $GLOBALS['repeat_count'] ?? 0);

        self::assertTrue(sg('KitchenLight.state', 'on'));
        $this->drainEvents($app);
        self::assertSame(2, $GLOBALS['repeat_count'] ?? 0);
        self::assertSame(1, $app->database()->connection()->table('property_history')->count());
    }

    private function boot(int $onlyNewValues): Application
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

        $db = $app->database()->connection();
        // Проверяется изолированная модель датчика с фиксированными id.
        foreach (['methods', 'properties', 'classes'] as $table) {
            $db->table($table)->delete();
        }
        $now = date('Y-m-d H:i:s');
        $db->table('classes')->insert([
            'id' => 1, 'title' => 'Sensor', 'parent_id' => 0, 'description' => '',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $db->table('objects')->insert([
            'id' => 1, 'title' => 'KitchenLight', 'class_id' => 1, 'location_id' => 0,
            'description' => '', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $db->table('properties')->insert([
            'id' => 1, 'class_id' => 1, 'object_id' => 0, 'title' => 'state', 'description' => '',
            'data_type' => 'string', 'keep_history' => 1, 'only_new_values' => $onlyNewValues,
            'on_change' => 'onState', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $db->table('methods')->insert([
            'id' => 1, 'class_id' => 1, 'object_id' => 0, 'title' => 'onState', 'description' => '',
            'code' => '$GLOBALS["repeat_count"] = ($GLOBALS["repeat_count"] ?? 0) + 1; return true;',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $GLOBALS['repeat_count'] = 0;

        return $app;
    }
}
