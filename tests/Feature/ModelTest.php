<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use LaIOT\Database\DatabaseManager;
use LaIOT\Models\Model;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ModelTest extends TestCase
{
    private DatabaseManager $database;

    protected function setUp(): void
    {
        $this->database = new DatabaseManager([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->database->schema()->create('demo_items', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function testBaseModelUsesLowercaseIdAndTimestamps(): void
    {
        $item = DemoItem::query()->create(['name' => 'test']);

        self::assertSame(1, $item->id);
        self::assertTrue($item->usesTimestamps());
        self::assertNotNull($item->created_at);
        self::assertSame('test', DemoItem::query()->find(1)?->name);
    }

    public function testTransactionRollsBackOnException(): void
    {
        try {
            $this->database->transaction(function (): void {
                DemoItem::query()->create(['name' => 'rolled back']);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        self::assertSame(0, DemoItem::query()->count());
    }
}

/**
 * @property int $id
 * @property string $name
 * @property \Carbon\CarbonImmutable|null $created_at
 */
final class DemoItem extends Model
{
    protected $table = 'demo_items';
}
