<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class CoreLocationsTest extends TestCase
{
    public function testLocationCanBeCreatedEditedAndDeleted(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        $app->database()->schema()->create('locations', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->integer('priority')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        $page = $app->handle(Request::create('/module/locations', 'GET'));
        self::assertSame(200, $page->getStatusCode());

        $create = $app->handle(Request::create('/module/locations/create', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Гостиная',
            'priority' => '10',
        ]));
        self::assertSame(302, $create->getStatusCode());
        $row = $app->database()->connection()->table('locations')->first();
        self::assertNotNull($row);

        $edit = $app->handle(Request::create('/module/locations/' . $row->id . '/edit', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Зал',
            'priority' => '20',
        ]));
        self::assertSame(302, $edit->getStatusCode());
        self::assertSame('Зал', $app->database()->connection()->table('locations')->where('id', $row->id)->value('title'));

        $delete = $app->handle(Request::create('/module/locations/' . $row->id . '/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNull($app->database()->connection()->table('locations')->whereNull('deleted_at')->first());
    }
}
