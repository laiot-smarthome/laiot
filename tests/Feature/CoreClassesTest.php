<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Http\Request;
use MajorDoMo\Database\Migrator;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * 2026-10-29 — CRUD проверяется на полной схеме вместо подавления ошибок чтения.
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
        (new Migrator($app->database(), $app->root() . '/database/migrations'))->migrate();

        SessionFixture::authenticate($app);

        $page = $app->handle(Request::create('/classes', 'GET'));
        self::assertSame(200, $page->getStatusCode());

        $create = $app->handle(Request::create('/classes/create', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Sensor',
            'description' => 'Базовый датчик',
        ]));
        self::assertSame(302, $create->getStatusCode());
        $row = $app->database()->connection()->table('classes')->where('title', 'Sensor')->first();
        self::assertNotNull($row);
        self::assertSame('Базовый датчик', $row->description);

        $edit = $app->handle(Request::create('/classes/' . $row->id . '/edit', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'TemperatureSensor',
        ]));
        self::assertSame(302, $edit->getStatusCode());
        self::assertSame('TemperatureSensor', $app->database()->connection()->table('classes')->where('id', $row->id)->value('title'));

        $delete = $app->handle(Request::create('/classes/' . $row->id . '/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNull($app->database()->connection()->table('classes')->where('id', $row->id)->whereNull('deleted_at')->first());
    }
}
