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
final class CoreMethodsTest extends TestCase
{
    public function testMethodCanBeCreatedAndDeleted(): void
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
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });
        $app->database()->schema()->create('methods', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('class_id')->default(0);
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        $app->database()->connection()->table('classes')->insert([
            'title' => 'Датчик',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        SessionFixture::authenticate($app);

        $page = $app->handle(Request::create('/methods', 'GET'));
        self::assertSame(200, $page->getStatusCode());

        $create = $app->handle(Request::create('/methods/create', 'POST', [
            '_token' => $session->csrfToken(),
            'class_id' => 1,
            'title' => 'update',
            'code' => '<?php echo 1;',
        ]));
        self::assertSame(302, $create->getStatusCode());
        self::assertSame('update', $app->database()->connection()->table('methods')->value('title'));

        $row = $app->database()->connection()->table('methods')->first();
        $delete = $app->handle(Request::create('/methods/' . $row->id . '/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNull($app->database()->connection()->table('methods')->whereNull('deleted_at')->first());
    }
}
