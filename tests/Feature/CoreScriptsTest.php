<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class CoreScriptsTest extends TestCase
{
    public function testScriptCanBeCreatedEditedAndDeleted(): void
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
            $table->boolean('run_periodically')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        $page = $app->handle(Request::create('/module/scripts', 'GET'));
        self::assertSame(200, $page->getStatusCode());

        $form = $app->handle(Request::create('/module/scripts/create', 'GET'));
        self::assertSame(200, $form->getStatusCode());
        self::assertStringContainsString('md-code-editor', (string)$form->getContent());

        $create = $app->handle(Request::create('/module/scripts/create', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Тестовый скрипт',
            'code' => '<?php echo 1;',
            'description' => 'demo',
            'category_id' => '0',
        ]));
        self::assertSame(302, $create->getStatusCode());
        $row = $app->database()->connection()->table('scripts')->first();
        self::assertNotNull($row);

        $edit = $app->handle(Request::create('/module/scripts/' . $row->id . '/edit', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Новый скрипт',
            'code' => '<?php echo 2;',
            'description' => 'demo2',
            'category_id' => '0',
        ]));
        self::assertSame(302, $edit->getStatusCode());
        self::assertSame('Новый скрипт', $app->database()->connection()->table('scripts')->where('id', $row->id)->value('title'));

        $delete = $app->handle(Request::create('/module/scripts/' . $row->id . '/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNull($app->database()->connection()->table('scripts')->whereNull('deleted_at')->first());
    }
}
