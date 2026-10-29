<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * Просмотр отдельного файла ошибки в админке.
 *
 * Файл читается только по имени внутри каталога ошибок: испорченный `log_file`
 * с `..` или абсолютным путём не должен открыть чужой файл.
 *
 * 2026-10-27 — тест создан вместе с просмотром файла ошибки.
 */
final class ErrorFileViewTest extends TestCase
{
    private string $directory = '';

    public function testAdminOpensErrorFile(): void
    {
        $app = $this->boot();
        $file = '20260101-120000-1-abcdef.log';
        file_put_contents($this->directory . '/' . $file, "message: boom\ntrace:\n#0 test");
        $app->database()->connection()->table('system_errors')->insert([
            'id' => 1,
            'level' => 'error',
            'message' => 'boom',
            'request_id' => 'req-1',
            'log_file' => $file,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $app->handle(Request::create('/errors/1/file', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('Ошибка #1', $html);
        self::assertStringContainsString('message: boom', $html);
        self::assertStringContainsString($file, $html);

        $list = (string)$app->handle(Request::create('/errors?flat=1', 'GET'))->getContent();
        self::assertStringContainsString('/errors/1/file', $list);
    }

    public function testMissingOrUnsafeFileReturns404(): void
    {
        $app = $this->boot();
        $connection = $app->database()->connection();
        $connection->table('system_errors')->insert([
            ['id' => 1, 'level' => 'error', 'message' => 'no file', 'request_id' => '', 'log_file' => null, 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 2, 'level' => 'error', 'message' => 'traversal', 'request_id' => '', 'log_file' => '../secret.log', 'created_at' => date('Y-m-d H:i:s')],
        ]);

        self::assertSame(404, $app->handle(Request::create('/errors/999/file', 'GET'))->getStatusCode());
        self::assertSame(404, $app->handle(Request::create('/errors/1/file', 'GET'))->getStatusCode());
        self::assertSame(404, $app->handle(Request::create('/errors/2/file', 'GET'))->getStatusCode());
    }

    private function boot(): Application
    {
        $this->directory = sys_get_temp_dir() . '/laiot_errors_' . bin2hex(random_bytes(4));
        if (!mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            self::fail('Не удалось создать каталог ошибок');
        }

        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'cache' => ['driver' => 'array'],
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'logging' => ['error_directory' => $this->directory],
        ]);
        $app->boot();
        $app->database()->schema()->create('system_errors', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('level', 16);
            $table->text('message');
            $table->string('request_id', 64)->default('');
            $table->string('log_file', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('deleted_at')->nullable();
        });
        SessionFixture::authenticate($app);

        return $app;
    }

    protected function tearDown(): void
    {
        if ($this->directory === '' || !is_dir($this->directory)) {
            return;
        }
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }
}
