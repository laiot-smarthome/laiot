<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Database\Model;
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
            $table->increments('ID');
            $table->string('NAME');
        });
    }

    public function testBaseModelUsesAlphaPrimaryKeyAndNoTimestamps(): void
    {
        $item = DemoItem::query()->create(['NAME' => 'test']);

        self::assertSame(1, $item->ID);
        self::assertFalse($item->usesTimestamps());
        self::assertSame('test', DemoItem::query()->find(1)?->NAME);
    }

    public function testTransactionRollsBackOnException(): void
    {
        try {
            $this->database->transaction(function (): void {
                DemoItem::query()->create(['NAME' => 'rolled back']);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        self::assertSame(0, DemoItem::query()->count());
    }
}

/**
 * @property int $ID
 * @property string $NAME
 */
final class DemoItem extends Model
{
    protected $table = 'demo_items';
}
