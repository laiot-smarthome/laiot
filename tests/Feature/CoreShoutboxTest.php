<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Http\Request;
use MajorDoMo\Database\Migrator;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class CoreShoutboxTest extends TestCase
{
    private Application $app;
    private ArraySession $session;

    protected function setUp(): void
    {
        $this->session = new ArraySession();
        $this->session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $this->session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $this->app->boot();
        (new Migrator($this->app->database(), $this->app->root() . '/database/migrations'))->migrate();
    }

    public function testCoreModulePageRenders(): void
    {
        $response = $this->app->handle(Request::create('/module/shoutbox', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Shoutbox', (string)$response->getContent());
        self::assertStringContainsString('md-admin-shell', (string)$response->getContent());
    }

    public function testSayWrapperWritesToModuleTable(): void
    {
        require_once dirname(__DIR__, 2) . '/src/Support/legacy_helpers.php';
        say('Сообщение через say()', 0, 1, 'test');

        $row = $this->app->database()->connection()
            ->table('module_shoutbox_messages')
            ->whereNull('deleted_at')
            ->first();
        self::assertNotNull($row);
        self::assertSame('Сообщение через say()', $row->message);
        self::assertSame('test', $row->source);
    }

    public function testMessageCanBeCreatedAndSoftDeleted(): void
    {
        $create = $this->app->handle(Request::create('/module/shoutbox', 'POST', [
            '_token' => $this->session->csrfToken(),
            'message' => 'Привет из core-модуля',
        ]));
        self::assertSame(302, $create->getStatusCode());

        $row = $this->app->database()->connection()
            ->table('module_shoutbox_messages')
            ->whereNull('deleted_at')
            ->first();
        self::assertNotNull($row);
        self::assertSame('Привет из core-модуля', $row->message);

        $delete = $this->app->handle(Request::create('/module/shoutbox/' . $row->id . '/delete', 'POST', [
            '_token' => $this->session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNull($this->app->database()->connection()
            ->table('module_shoutbox_messages')
            ->whereNull('deleted_at')
            ->first());
    }
}
