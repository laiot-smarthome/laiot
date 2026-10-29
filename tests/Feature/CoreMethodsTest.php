<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * Методы класса: сохранение и проверка синтаксиса кода.
 *
 * 2026-10-27 — невалидный код метода не сохраняется, строка подсвечивается.
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 */
final class CoreMethodsTest extends TestCase
{
    public function testMethodCanBeCreatedAndDeleted(): void
    {
        [$app, $session] = $this->boot();

        $create = $app->handle(Request::create('/methods/create', 'POST', [
            '_token' => $session->csrfToken(),
            'class_id' => 1,
            'title' => 'update',
            'code' => 'echo 1;',
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

    public function testMethodWithSyntaxErrorIsNotSaved(): void
    {
        [$app, $session] = $this->boot();

        $create = $app->handle(Request::create('/methods/create', 'POST', [
            '_token' => $session->csrfToken(),
            'class_id' => 1,
            'title' => 'broken',
            'code' => "echo 1;\nif (true {\n    echo 2;\n}",
        ]));

        self::assertSame(422, $create->getStatusCode());
        $html = (string)$create->getContent();
        self::assertStringContainsString('Синтаксическая ошибка', $html);
        self::assertStringContainsString('Строка 2', $html);
        self::assertStringContainsString('md-code-editor__error', $html);
        self::assertSame(0, (int)$app->database()->connection()->table('methods')->count());
    }

    /**
     * @return array{0:Application,1:ArraySession}
     */
    private function boot(): array
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
            $table->integer('parent_id')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        $app->database()->schema()->create('methods', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('class_id')->default(0);
            $table->integer('object_id')->default(0);
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

        return [$app, $session];
    }
}
