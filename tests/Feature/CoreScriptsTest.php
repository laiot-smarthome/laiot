<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Scripts\ScriptRepository;
use PHPUnit\Framework\TestCase;

/**
 * Сценарии: CRUD, проверка синтаксиса, категории в модалке, экспорт и импорт.
 *
 * 2026-10-27 — категории управляются Yoyo-компонентом, вместо расписания —
 *              системное событие; невалидный код не сохраняется.
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 * 2026-10-26 — добавлены категории и JSON-импорт/экспорт.
 */
final class CoreScriptsTest extends TestCase
{
    protected function tearDown(): void
    {
        Yoyo::request()->reset();
        unset($_SERVER['HTTP_HX_REQUEST'], $_SERVER['REQUEST_METHOD']);
        $_REQUEST = [];
        parent::tearDown();
    }

    public function testScriptCanBeCreatedEditedAndDeleted(): void
    {
        [$app, $session] = $this->boot();

        $page = $app->handle(Request::create('/scripts', 'GET'));
        self::assertSame(200, $page->getStatusCode());

        $form = $app->handle(Request::create('/scripts/create', 'GET'));
        self::assertSame(200, $form->getStatusCode());
        self::assertStringContainsString('md-code-editor', (string)$form->getContent());

        $create = $app->handle(Request::create('/scripts/create', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Тестовый скрипт',
            'code' => 'echo 1;',
            'description' => 'demo',
            'category_id' => '0',
            'event_name' => 'system.minute',
        ]));
        self::assertSame(302, $create->getStatusCode());
        $row = $app->database()->connection()->table('scripts')->first();
        self::assertNotNull($row);
        self::assertSame('system.minute', (string)$row->event_name);

        $edit = $app->handle(Request::create('/scripts/' . $row->id . '/edit', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Новый скрипт',
            'code' => 'echo 2;',
            'description' => 'demo2',
            'category_id' => '0',
            'event_name' => '',
        ]));
        self::assertSame(302, $edit->getStatusCode());
        self::assertSame('Новый скрипт', $app->database()->connection()->table('scripts')->where('id', $row->id)->value('title'));
        self::assertSame('', (string)$app->database()->connection()->table('scripts')->where('id', $row->id)->value('event_name'));

        $delete = $app->handle(Request::create('/scripts/' . $row->id . '/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNull($app->database()->connection()->table('scripts')->whereNull('deleted_at')->first());
    }

    public function testInvalidCodeIsNotSaved(): void
    {
        [$app, $session] = $this->boot();

        $create = $app->handle(Request::create('/scripts/create', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Сломанный скрипт',
            'code' => "echo 1;\nif (true {\n    echo 2;\n}",
            'description' => '',
            'category_id' => '0',
            'event_name' => '',
        ]));

        self::assertSame(422, $create->getStatusCode());
        $html = (string)$create->getContent();
        self::assertStringContainsString('Синтаксическая ошибка', $html);
        self::assertStringContainsString('Строка 2', $html);
        self::assertStringContainsString('md-code-editor__error', $html);
        self::assertSame(0, (int)$app->database()->connection()->table('scripts')->count());

        // Код с открывающим тегом тоже невалиден: ядро добавляет тег само.
        $withTag = $app->handle(Request::create('/scripts/create', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'С тегом',
            'code' => '<?php echo 1;',
            'description' => '',
            'category_id' => '0',
            'event_name' => '',
        ]));
        self::assertSame(422, $withTag->getStatusCode());
        self::assertStringContainsString('Уберите открывающий тег', (string)$withTag->getContent());
        self::assertSame(0, (int)$app->database()->connection()->table('scripts')->count());
    }

    public function testScriptCategoriesAreManagedInModal(): void
    {
        [$app, $session] = $this->boot();
        $scripts = $app->container()->make(ScriptRepository::class);

        $page = $app->handle(Request::create('/scripts', 'GET'));
        $html = (string)$page->getContent();
        self::assertStringContainsString('yoyo:name="script-categories"', $html);
        self::assertStringNotContainsString('action="/scripts/categories"', $html);

        $this->yoyo($app, $session, [
            'component' => 'script-categories/show',
            'yoyo-id' => 'test-script-categories',
        ]);
        $this->yoyo($app, $session, [
            'component' => 'script-categories/startCreate',
            'yoyo-id' => 'test-script-categories',
            'view' => 'list',
        ]);
        $this->yoyo($app, $session, [
            'component' => 'script-categories/save',
            'yoyo-id' => 'test-script-categories',
            'view' => 'form',
            'title' => 'Освещение',
        ]);
        $categoryId = (int)$app->database()->connection()->table('script_categories')->value('id');
        self::assertGreaterThan(0, $categoryId);

        $scriptId = $scripts->create('Свет утром', 'cm("Спальня.turnOn");', '', 0);
        $scripts->update($scriptId, 'Свет утром', 'cm("Спальня.turnOn");', '', $categoryId);
        self::assertSame(1, $scripts->categoryCounts()[$categoryId] ?? 0);

        $this->yoyo($app, $session, [
            'component' => 'script-categories/startEdit',
            'yoyo-id' => 'test-script-categories',
            'view' => 'list',
            'actionArgs' => json_encode([$categoryId], JSON_THROW_ON_ERROR),
        ]);
        $this->yoyo($app, $session, [
            'component' => 'script-categories/save',
            'yoyo-id' => 'test-script-categories',
            'view' => 'form',
            'editing' => (string)$categoryId,
            'title' => 'Свет',
        ]);
        self::assertSame('Свет', $app->database()->connection()->table('script_categories')->value('title'));

        $this->yoyo($app, $session, [
            'component' => 'script-categories/remove',
            'yoyo-id' => 'test-script-categories',
            'confirming' => (string)$categoryId,
            'actionArgs' => json_encode([$categoryId], JSON_THROW_ON_ERROR),
        ]);
        self::assertNull($app->database()->connection()->table('script_categories')->whereNull('deleted_at')->first());
        self::assertSame(0, (int)$app->database()->connection()->table('scripts')->where('id', $scriptId)->value('category_id'));
    }

    public function testScriptsCanBeExportedAndImported(): void
    {
        [$app, $session] = $this->boot();
        $scripts = $app->container()->make(ScriptRepository::class);
        $scripts->create('Утренний свет', 'cm("Спальня.turnOn");', 'Включает свет', 0, 'system.day');

        $export = $app->handle(Request::create('/scripts/export', 'GET'));
        self::assertSame(200, $export->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $export->headers->get('Content-Type'));
        self::assertStringContainsString('attachment;', (string)$export->headers->get('Content-Disposition'));
        $decoded = json_decode((string)$export->getContent(), true);
        self::assertIsArray($decoded);
        self::assertSame('Утренний свет', $decoded[0]['title']);
        self::assertSame('system.day', $decoded[0]['event_name']);

        $importJson = json_encode([
            ['title' => 'Импортированный', 'code' => 'echo 1;', 'description' => 'из JSON', 'event_name' => 'system.hour'],
            ['title' => 'Утренний свет', 'code' => 'дубликат'],
            ['title' => 'Сломанный', 'code' => 'if (', 'description' => 'ошибка'],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $import = $app->handle(Request::create('/scripts/import', 'POST', [
            '_token' => $session->csrfToken(),
            'payload' => $importJson,
        ]));
        self::assertSame(302, $import->getStatusCode());
        self::assertNotNull($scripts->findByTitle('Импортированный'));
        self::assertSame('system.hour', (string)$scripts->findByTitle('Импортированный')['event_name']);
        self::assertNull($scripts->findByTitle('Сломанный'));
        self::assertSame(2, (int)$app->database()->connection()->table('scripts')->whereNull('deleted_at')->count());
        $location = rawurldecode((string)$import->headers->get('Location'));
        self::assertStringContainsString('Пропущены (уже есть): Утренний свет', $location);
        self::assertStringContainsString('Не сохранены: Сломанный', $location);

        $bad = $app->handle(Request::create('/scripts/import', 'POST', [
            '_token' => $session->csrfToken(),
            'payload' => 'не json',
        ]));
        self::assertSame(302, $bad->getStatusCode());
        self::assertStringContainsString('/scripts/import?error=', (string)$bad->headers->get('Location'));
    }

    /**
     * @param array<string, string> $request
     */
    private function yoyo(Application $app, ArraySession $session, array $request): void
    {
        Yoyo::request()->reset();
        $_REQUEST = $request;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        Yoyo::request()->mock($_REQUEST, $_SERVER);

        $response = $app->handle(Request::create('/yoyo', 'POST', [], [], [], [
            'HTTP_X_CSRF_TOKEN' => $session->csrfToken(),
        ]));
        self::assertSame(200, $response->getStatusCode());

        Yoyo::request()->reset();
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

        return [$app, $session];
    }
}
