<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use MajorDoMo\Database\SpikeNote;
use PHPUnit\Framework\TestCase;

final class EloquentSpikeTest extends TestCase
{
    private Capsule $capsule;

    protected function setUp(): void
    {
        $this->capsule = new Capsule();
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
        $this->capsule->schema()->create('spike_notes', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('message');
        });
    }

    protected function tearDown(): void
    {
        $this->capsule->getConnection()->disconnect();
        parent::tearDown();
    }

    public function testModelCanCreateAndRead(): void
    {
        SpikeNote::query()->create(['message' => 'hello']);

        $note = SpikeNote::query()->first();

        self::assertNotNull($note);
        self::assertSame('hello', $note->message);
        self::assertSame(1, $note->id);
    }
}
