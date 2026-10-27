<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * Временный пробник модалки для скриншота. Удаляется.
 */
final class TmpModalProbeTest extends TestCase
{
    public function testRender(): void
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
        $app->database()->schema()->create('script_categories', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });
        $app->database()->schema()->create('scripts', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->text('code')->nullable();
            $table->text('description')->nullable();
            $table->integer('category_id')->default(0);
            $table->string('event_name', 191)->default('');
            $table->timestamps();
            $table->softDeletes();
        });
        SessionFixture::authenticate($app);

        $connection = $app->database()->connection();
        $now = date('Y-m-d H:i:s');
        $connection->table('script_categories')->insert([
            ['title' => 'Освещение', 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Климат', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('scripts')->insert([
            'title' => 'Утренний свет', 'code' => "sg('Кухня.temperature', 23);", 'description' => 'Включает свет',
            'category_id' => 1, 'event_name' => 'system.minute', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $page = (string)$app->handle(Request::create('/scripts', 'GET'))->getContent();

        foreach ([
            'probe-modal.html' => ['component' => 'script-categories/show'],
            'probe-modal-create.html' => ['component' => 'script-categories/startCreate', 'open' => 'true', 'view' => 'list'],
            'probe-modal-edit.html' => ['component' => 'script-categories/startEdit', 'open' => 'true', 'view' => 'list', 'actionArgs' => '[1]'],
            'probe-modal-delete.html' => ['component' => 'script-categories/requestDelete', 'actionArgs' => '[1]'],
        ] as $file => $request) {
            Yoyo::request()->reset();
            $_REQUEST = $request + ['yoyo-id' => 'probe'];
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['HTTP_HX_REQUEST'] = 'true';
            Yoyo::request()->mock($_REQUEST, $_SERVER);
            $modal = (string)$app->handle(Request::create('/yoyo', 'POST', [], [], [], [
                'HTTP_X_CSRF_TOKEN' => $session->csrfToken(),
            ]))->getContent();
            Yoyo::request()->reset();

            file_put_contents($app->root() . '/public/' . $file, str_replace('</main>', $modal . '</main>', $page));
        }

        self::assertFileExists($app->root() . '/public/probe-modal-edit.html');
    }
}
