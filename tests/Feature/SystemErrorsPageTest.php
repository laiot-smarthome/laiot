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
 * Единая панель разбора ошибки и Yoyo-переключение записей.
 *
 * 2026-10-27 — проверяются место, стек, контекст, исходный файл и переход
 * между группой и отдельной записью без страницы файла.
 * 2026-10-29 — общий каркас таблиц и точный счётчик показанных повторов.
 */
final class SystemErrorsPageTest extends TestCase
{
    private string $directory = '';

    private Application $app;

    private ArraySession $session;

    protected function setUp(): void
    {
        Yoyo::request()->reset();
        $this->directory = sys_get_temp_dir() . '/mydomo_errors_page_' . bin2hex(random_bytes(4));
        if (!mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            self::fail('Не удалось создать каталог ошибок');
        }

        $this->session = new ArraySession();
        $this->session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'view_cache' => sys_get_temp_dir() . '/mydomo-views-' . bin2hex(random_bytes(4)),
            'log' => 'php://stderr',
            'cache' => ['driver' => 'array'],
            'session' => $this->session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'logging' => ['error_directory' => $this->directory],
        ]);
        $this->app->boot();
        $this->app->database()->schema()->create('system_errors', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('level', 16)->default('error');
            $table->text('message');
            $table->text('context')->nullable();
            $table->string('request_id', 64)->default('');
            $table->string('log_file', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        SessionFixture::authenticate($this->app);
    }

    protected function tearDown(): void
    {
        Yoyo::request()->reset();
        unset($_SERVER['HTTP_HX_REQUEST'], $_SERVER['REQUEST_METHOD']);
        $_REQUEST = [];
        if (is_dir($this->directory)) {
            foreach (glob($this->directory . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->directory);
        }
        parent::tearDown();
    }

    public function testListAndDetailShowWhereWhenStackContextAndRawFile(): void
    {
        $this->seedError(1, 'boom', '2026-10-27 12:34:56', 'req-1');

        $list = (string)$this->app->handle(Request::create('/errors?flat=1', 'GET'))->getContent();
        self::assertStringContainsString('/errors/1/file', $list);
        self::assertStringContainsString('boom', $list);
        self::assertStringNotContainsString('Скопировать для AI', $list);

        $response = $this->yoyo('system-errors/select', [1]);
        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('Скопировать для AI', $html);
        self::assertStringContainsString('MyDoMo: ошибка #1', $html);
        self::assertStringContainsString('RuntimeException', $html);
        self::assertStringContainsString('/app/src/Foo.php:42', $html);
        self::assertStringContainsString('Стек', $html);
        self::assertStringContainsString('#0', $html);
        self::assertStringContainsString('Kitchen', $html);
        self::assertStringContainsString('Исходный файл ошибки', $html);
        self::assertStringContainsString('27.10.2026 12:34:56', $html);
        self::assertStringContainsString('Повторов', $html);
    }

    public function testYoyoOpensGroupAndSelectsRecordWithoutPageTransition(): void
    {
        $this->seedError(1, 'boom', '2026-10-27 12:34:56', 'req-1');
        $this->seedError(2, 'boom', '2026-10-27 12:40:00', 'req-2');

        $page = (string)$this->app->handle(Request::create('/errors', 'GET'))->getContent();
        self::assertStringContainsString('Групп: 1', $page);
        self::assertStringContainsString('2 повт.', $page);
        self::assertStringContainsString('aria-label="Группы ошибок"', $page);
        self::assertStringContainsString('Request ID: <code>req-2</code>', $page);
        self::assertStringNotContainsString('Request ID: <code>req-1</code>', $page);

        $response = $this->yoyo('system-errors/openGroup', [2]);
        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('Ошибка #2', $html);
        self::assertStringContainsString('req-2', $html);
        self::assertStringContainsString('Повторов', $html);
        self::assertStringContainsString('Стек', $html);
        self::assertStringContainsString('/app/src/Foo.php:42', $html);
        self::assertStringContainsString('К списку ошибок', $html);
        self::assertStringContainsString('aria-label="Повторы"', $html);
        self::assertStringContainsString('aria-current="true"', $html);
    }

    public function testOccurrencesFooterMatchesTheHundredRowsActuallyShown(): void
    {
        for ($id = 1; $id <= 101; $id++) {
            $this->seedError($id, 'boom', '2026-10-27 12:34:56', 'req-' . $id);
        }

        $response = $this->yoyo('system-errors/openGroup', [101]);
        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('Показаны последние 100 из 101 повторов.', $html);
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        self::assertSame(100, $xpath->query('//div[@aria-label="Повторы"]//tbody/tr')->length);
    }

    public function testYoyoSelectChangesDetail(): void
    {
        $this->seedError(1, 'first', '2026-10-27 12:34:56', 'req-1');
        $this->seedError(2, 'second', '2026-10-27 12:40:00', 'req-2');

        $response = $this->yoyo('system-errors/select', [1]);
        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('Ошибка #1', $html);
        self::assertStringContainsString('first', $html);
        self::assertStringContainsString('req-1', $html);
        self::assertStringContainsString('Скопировать для AI', $html);
    }

    public function testGroupDetailPreservesSearchAndListMode(): void
    {
        $this->seedError(1, 'first', '2026-10-27 12:34:56', 'req-1');
        $this->seedError(2, 'second', '2026-10-27 12:40:00', 'req-2');

        $response = $this->yoyo('system-errors/openGroup', [1], [
            'search' => 'first', 'level' => 'error', 'flat' => false,
        ]);
        $html = (string)$response->getContent();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('name="search" value="first"', $html);
        self::assertStringContainsString('name="level" value="error"', $html);
        self::assertStringNotContainsString('hx-post="render"', $html);

        $response = $this->yoyo('system-errors/back', [], [
            'search' => 'first', 'level' => 'error', 'flat' => false, 'selectedId' => 1,
        ]);
        $html = (string)$response->getContent();
        self::assertStringContainsString('Группы ошибок', $html);
        self::assertStringContainsString('Групп: 1', $html);
        self::assertStringContainsString('name="search" value="first"', $html);
        self::assertStringNotContainsString('Request ID: <code>req-2</code>', $html);
        self::assertStringNotContainsString('hx-post="render"', $html);
    }

    public function testBackReturnsToTheListScreen(): void
    {
        $this->seedError(1, 'boom', '2026-10-27 12:34:56', 'req-1');

        $response = $this->yoyo('system-errors/back', []);
        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('Группы ошибок', $html);
        self::assertStringNotContainsString('Скопировать для AI', $html);
    }

    /**
     * @param list<int> $arguments
     * @param array<string, string|int|bool> $state
     */
    private function yoyo(string $component, array $arguments, array $state = []): \Symfony\Component\HttpFoundation\Response
    {
        $_REQUEST = $state + [
            'component' => $component,
            'yoyo-id' => 'test-system-errors',
            'actionArgs' => $arguments,
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        Yoyo::request()->mock($_REQUEST, $_SERVER);

        return $this->app->handle(Request::create('/yoyo', 'POST', [], [], [], [
            'HTTP_X_CSRF_TOKEN' => $this->session->csrfToken(),
        ]));
    }

    private function seedError(int $id, string $message, string $createdAt, string $requestId): void
    {
        $file = '20261027-123456-' . $id . '-abcdef.log';
        $content = "=== MyDoMo error 2026-10-27 12:34:56 ===\n"
            . 'id: ' . $id . "\n"
            . "level: error\n"
            . 'request_id: ' . $requestId . "\n"
            . 'message: ' . $message . "\n"
            . 'error: ' . $message . "\n"
            . "class: RuntimeException\n"
            . "file: /app/src/Foo.php:42\n"
            . "path: /demo\n"
            . 'context: {"object":"Kitchen","attempt":3}' . "\n"
            . "trace:\n"
            . "#0 /app/src/Foo.php(42): MajorDoMo\\Demo->run()\n"
            . "#1 {main}\n";
        file_put_contents($this->directory . '/' . $file, $content);
        $this->app->database()->connection()->table('system_errors')->insert([
            'id' => $id,
            'level' => 'error',
            'message' => $message,
            'context' => '{"object":"Kitchen"}',
            'request_id' => $requestId,
            'log_file' => $file,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
