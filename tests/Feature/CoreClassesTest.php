<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 */
final class CoreClassesTest extends TestCase
{
    public function testClassCanBeCreatedEditedAndDeleted(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'cache' => ['driver' => 'array'],
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        $app->database()->schema()->create('classes', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->unique();
            $table->integer('parent_id')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        SessionFixture::authenticate($app);

        $page = $app->handle(Request::create('/classes', 'GET'));
        self::assertSame(200, $page->getStatusCode());

        $create = $app->handle(Request::create('/classes/create', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Датчик',
        ]));
        self::assertSame(302, $create->getStatusCode());
        $row = $app->database()->connection()->table('classes')->first();
        self::assertNotNull($row);

        $edit = $app->handle(Request::create('/classes/' . $row->id . '/edit', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Датчик температуры',
        ]));
        self::assertSame(302, $edit->getStatusCode());
        self::assertSame('Датчик температуры', $app->database()->connection()->table('classes')->where('id', $row->id)->value('title'));

        $delete = $app->handle(Request::create('/classes/' . $row->id . '/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNull($app->database()->connection()->table('classes')->whereNull('deleted_at')->first());
    }
}
