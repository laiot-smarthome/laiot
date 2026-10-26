<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Scripts\ScriptRepository;
use PHPUnit\Framework\TestCase;

/**
 * Сценарии: CRUD, категории, экспорт и импорт.
 *
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 * 2026-10-26 — добавлены категории и JSON-импорт/экспорт.
 */
final class CoreScriptsTest extends TestCase
{
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
            'code' => '<?php echo 1;',
            'description' => 'demo',
            'category_id' => '0',
        ]));
        self::assertSame(302, $create->getStatusCode());
        $row = $app->database()->connection()->table('scripts')->first();
        self::assertNotNull($row);

        $edit = $app->handle(Request::create('/scripts/' . $row->id . '/edit', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Новый скрипт',
            'code' => '<?php echo 2;',
            'description' => 'demo2',
            'category_id' => '0',
        ]));
        self::assertSame(302, $edit->getStatusCode());
        self::assertSame('Новый скрипт', $app->database()->connection()->table('scripts')->where('id', $row->id)->value('title'));

        $delete = $app->handle(Request::create('/scripts/' . $row->id . '/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNull($app->database()->connection()->table('scripts')->whereNull('deleted_at')->first());
    }

    public function testScriptCategoriesCanBeManaged(): void
    {
        [$app, $session] = $this->boot();
        $scripts = $app->container()->make(ScriptRepository::class);
        $scriptId = $scripts->create('Свет утром', 'cm("Спальня.turnOn");', '', 0, false);

        $create = $app->handle(Request::create('/scripts/categories', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Освещение',
        ]));
        self::assertSame(302, $create->getStatusCode());
        $categoryId = (int)$app->database()->connection()->table('script_categories')->value('id');
        self::assertGreaterThan(0, $categoryId);

        $scripts->update($scriptId, 'Свет утром', 'cm("Спальня.turnOn");', '', $categoryId, false);
        self::assertSame(1, $scripts->categoryCounts()[$categoryId] ?? 0);

        $rename = $app->handle(Request::create('/scripts/categories/' . $categoryId, 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Свет',
        ]));
        self::assertSame(302, $rename->getStatusCode());
        self::assertSame('Свет', $app->database()->connection()->table('script_categories')->value('title'));

        $delete = $app->handle(Request::create('/scripts/categories/' . $categoryId . '/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNull($app->database()->connection()->table('script_categories')->whereNull('deleted_at')->first());
        self::assertSame(0, (int)$app->database()->connection()->table('scripts')->where('id', $scriptId)->value('category_id'));
    }

    public function testScriptsCanBeExportedAndImported(): void
    {
        [$app, $session] = $this->boot();
        $scripts = $app->container()->make(ScriptRepository::class);
        $scripts->create('Утренний свет', 'cm("Спальня.turnOn");', 'Включает свет', 0, false);

        $export = $app->handle(Request::create('/scripts/export', 'GET'));
        self::assertSame(200, $export->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $export->headers->get('Content-Type'));
        self::assertStringContainsString('attachment;', (string)$export->headers->get('Content-Disposition'));
        $decoded = json_decode((string)$export->getContent(), true);
        self::assertIsArray($decoded);
        self::assertSame('Утренний свет', $decoded[0]['title']);

        $importJson = json_encode([
            ['title' => 'Импортированный', 'code' => 'echo 1;', 'description' => 'из JSON'],
            ['title' => 'Утренний свет', 'code' => 'дубликат'],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $import = $app->handle(Request::create('/scripts/import', 'POST', [
            '_token' => $session->csrfToken(),
            'payload' => $importJson,
        ]));
        self::assertSame(302, $import->getStatusCode());
        self::assertNotNull($scripts->findByTitle('Импортированный'));
        self::assertSame(2, (int)$app->database()->connection()->table('scripts')->whereNull('deleted_at')->count());

        $bad = $app->handle(Request::create('/scripts/import', 'POST', [
            '_token' => $session->csrfToken(),
            'payload' => 'не json',
        ]));
        self::assertSame(302, $bad->getStatusCode());
        self::assertStringContainsString('/scripts/import?error=', (string)$bad->headers->get('Location'));
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
            $table->boolean('run_periodically')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        SessionFixture::authenticate($app);

        return [$app, $session];
    }
}
