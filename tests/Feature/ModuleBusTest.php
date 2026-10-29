<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Modules\ModuleBus;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ModuleBusTest extends TestCase
{
    private DatabaseManager $database;
    private int $now = 1_700_000_000;

    protected function setUp(): void
    {
        ModuleBus::resetListeners();
        $this->database = new DatabaseManager([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function tearDown(): void
    {
        ModuleBus::resetListeners();
        parent::tearDown();
    }

    public function testPublishAndReadAcrossModules(): void
    {
        $keenetic = $this->bus('keenetic');
        $keenetic->publish('router.42.online', ['online' => true], 300);

        $other = $this->bus('other');
        $event = $other->latest('module.keenetic.router.42.online');

        self::assertNotNull($event);
        self::assertSame('module.keenetic.router.42.online', $event['topic']);
        self::assertSame('keenetic', $event['module']);
        self::assertTrue($event['payload']['online']);
    }

    public function testRetainedEventExpires(): void
    {
        $this->bus('keenetic')->publish('router.42.online', ['online' => true], 60);
        self::assertNotNull($this->bus('other')->latest('module.keenetic.router.42.online'));

        $this->now += 61;

        self::assertNull($this->bus('other')->latest('module.keenetic.router.42.online'));
    }

    public function testModuleCannotPublishIntoAnotherNamespace(): void
    {
        $this->expectException(RuntimeException::class);
        $this->bus('keenetic')->publish('module.other.status', ['ok' => true]);
    }

    public function testSubscriberReceivesMatchingTopic(): void
    {
        $received = [];
        $this->bus('other')->subscribe('module.keenetic.router.*', static function (string $topic, array $payload) use (&$received): void {
            $received[] = [$topic, $payload];
        });

        $this->bus('keenetic')->publish('router.42.online', ['online' => true], 300);

        self::assertCount(1, $received);
        self::assertSame('module.keenetic.router.42.online', $received[0][0]);
        self::assertTrue($received[0][1]['online']);
    }

    private function bus(string $id): ModuleBus
    {
        return new ModuleBus($id, $this->database, fn (): int => $this->now);
    }
}
