<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use LaIOT\Services\Scripts\ScriptExecutor;
use LaIOT\Services\Scripts\ScriptRepository;
use PHPUnit\Framework\TestCase;

/**
 * Сценарии: CRUD, проверка синтаксиса, категории в модалке, экспорт и импорт.
 *
 * 2026-10-30 — ошибка фикстуры однозначно расположена во второй строке на PHP 8.2–8.4.
 * 2026-10-28 — результаты действий проверяются в событии Notify.
 * 2026-10-27 — категории управляются Yoyo-компонентом, вместо расписания —
 *              системное событие; невалидный код не сохраняется.
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 * 2026-10-26 — добавлены категории и JSON-импорт/экспорт.
 */
final class CoreScriptsTest extends TestCase
{
    private string $browserEventText = '';

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
        $formHtml = (string)$form->getContent();
        self::assertStringContainsString('md-code-editor', $formHtml);
        self::assertStringContainsString('formaction="/scripts/run"', $formHtml);
        self::assertStringContainsString('name="use_core"', $formHtml);
        self::assertStringContainsString('Подключать ядро', $formHtml);

        $create = $app->handle(Request::create('/scripts/create', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Тестовый скрипт',
            'code' => 'echo 1;',
            'description' => 'demo',
            'category_id' => '0',
            'event_name' => 'system.minute',
            'use_core' => '0',
        ]));
        self::assertSame(302, $create->getStatusCode());
        $row = $app->database()->connection()->table('scripts')->first();
        self::assertNotNull($row);
        self::assertSame('system.minute', (string)$row->event_name);
        self::assertSame(0, (int)$row->use_core);

        $edit = $app->handle(Request::create('/scripts/' . $row->id . '/edit', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Новый скрипт',
            'code' => 'echo 2;',
            'description' => 'demo2',
            'category_id' => '0',
            'event_name' => '',
            'use_core' => '1',
        ]));
        self::assertSame(302, $edit->getStatusCode());
        self::assertSame('Новый скрипт', $app->database()->connection()->table('scripts')->where('id', $row->id)->value('title'));
        self::assertSame('', (string)$app->database()->connection()->table('scripts')->where('id', $row->id)->value('event_name'));
        self::assertSame(1, (int)$app->database()->connection()->table('scripts')->where('id', $row->id)->value('use_core'));

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
            'code' => "echo 1;\n\$value = ;",
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

    public function testDraftCanBeRunWithCoreAndResultIsShown(): void
    {
        [$app, $session] = $this->boot();

        $response = $app->handle(Request::create('/scripts/run', 'POST', [
            '_token' => $session->csrfToken(),
            'id' => '0',
            'title' => 'Черновик',
            'code' => 'echo "привет"; return 21 * 2;',
            'description' => '',
            'category_id' => '0',
            'event_name' => '',
            'use_core' => '1',
        ]));

        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('Результат выполнения', $html);
        self::assertStringContainsString('Выполнено', $html);
        self::assertStringContainsString('привет', $html);
        self::assertStringContainsString('42', $html);

        // Запуск черновика не сохраняет сценарий.
        self::assertSame(0, (int)$app->database()->connection()->table('scripts')->count());

        $syntax = $app->handle(Request::create('/scripts/run', 'POST', [
            '_token' => $session->csrfToken(),
            'id' => '0',
            'title' => 'Черновик',
            'code' => 'if (',
            'description' => '',
            'category_id' => '0',
            'event_name' => '',
            'use_core' => '1',
        ]));
        self::assertSame(422, $syntax->getStatusCode());
        self::assertStringContainsString('Синтаксическая ошибка', (string)$syntax->getContent());
    }

    public function testScriptWithoutCoreRunsAsPlainPhp(): void
    {
        [$app, $session] = $this->boot();
        $executor = $app->container()->make(ScriptExecutor::class);
        $code = 'return function_exists("sg") ? "CORE_AVAILABLE" : "PLAIN_ONLY";';

        $withCore = $executor->execute($code, [], true);
        self::assertTrue($withCore['ok']);
        self::assertSame("'CORE_AVAILABLE'", $withCore['result']);

        $plain = $executor->execute($code, [], false);
        self::assertTrue($plain['ok']);
        self::assertSame("'PLAIN_ONLY'", $plain['result']);

        // $params передаётся и в отдельный процесс.
        $params = $executor->execute('return $params["event"] ?? "none";', ['event' => 'module.demo.ready'], false);
        self::assertTrue($params['ok']);
        self::assertSame("'module.demo.ready'", $params['result']);

        // Тот же выбор через форму: флаг use_core=0 доходит до выполнения.
        $response = $app->handle(Request::create('/scripts/run', 'POST', [
            '_token' => $session->csrfToken(),
            'id' => '0',
            'title' => 'Без ядра',
            'code' => $code,
            'description' => '',
            'category_id' => '0',
            'event_name' => '',
            'use_core' => '0',
        ]));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Выполнено', (string)$response->getContent());
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

        // «Добавить» заменяет список формой, а сохранение возвращает список.
        $form = $this->yoyo($app, $session, [
            'component' => 'script-categories/startCreate',
            'yoyo-id' => 'test-script-categories',
            'open' => 'true',
            'view' => 'list',
        ]);
        self::assertStringContainsString('Новая категория', $form);
        self::assertStringContainsString('name="title"', $form);
        self::assertStringNotContainsString('Всего категорий:', $form);

        $list = $this->yoyo($app, $session, [
            'component' => 'script-categories/save',
            'yoyo-id' => 'test-script-categories',
            'open' => 'true',
            'view' => 'form',
            'title' => 'Освещение',
        ]);
        self::assertStringContainsString('Категория создана.', $this->browserEventText);
        self::assertStringContainsString('Всего категорий: 1', $list);
        $categoryId = (int)$app->database()->connection()->table('script_categories')->value('id');
        self::assertGreaterThan(0, $categoryId);

        $scriptId = $scripts->create('Свет утром', 'cm("Спальня.turnOn");', '', 0);
        $scripts->update($scriptId, 'Свет утром', 'cm("Спальня.turnOn");', '', $categoryId);
        self::assertSame(1, $scripts->categoryCounts()[$categoryId] ?? 0);

        $renameForm = $this->yoyo($app, $session, [
            'component' => 'script-categories/startEdit',
            'yoyo-id' => 'test-script-categories',
            'open' => 'true',
            'view' => 'list',
            'actionArgs' => json_encode([$categoryId], JSON_THROW_ON_ERROR),
        ]);
        self::assertStringContainsString('Переименование категории', $renameForm);
        self::assertStringContainsString('value="Освещение"', $renameForm);

        $renamed = $this->yoyo($app, $session, [
            'component' => 'script-categories/save',
            'yoyo-id' => 'test-script-categories',
            'open' => 'true',
            'view' => 'form',
            'editing' => (string)$categoryId,
            'title' => 'Свет',
        ]);
        self::assertSame('Свет', $app->database()->connection()->table('script_categories')->value('title'));
        self::assertStringContainsString('Категория переименована.', $this->browserEventText);
        self::assertStringContainsString('Всего категорий: 1', $renamed);

        $deleted = $this->yoyo($app, $session, [
            'component' => 'script-categories/remove',
            'yoyo-id' => 'test-script-categories',
            'open' => 'true',
            'confirming' => (string)$categoryId,
            'actionArgs' => json_encode([$categoryId], JSON_THROW_ON_ERROR),
        ]);
        self::assertStringContainsString('Категория удалена', $this->browserEventText);
        self::assertNull($app->database()->connection()->table('script_categories')->whereNull('deleted_at')->first());
        self::assertSame(0, (int)$app->database()->connection()->table('scripts')->where('id', $scriptId)->value('category_id'));
    }

    public function testScriptsCanBeExportedAndImported(): void
    {
        [$app, $session] = $this->boot();
        $scripts = $app->container()->make(ScriptRepository::class);
        $scripts->create('Утренний свет', 'cm("Спальня.turnOn");', 'Включает свет', 0, 'system.day', false);

        $export = $app->handle(Request::create('/scripts/export', 'GET'));
        self::assertSame(200, $export->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $export->headers->get('Content-Type'));
        self::assertStringContainsString('attachment;', (string)$export->headers->get('Content-Disposition'));
        $decoded = json_decode((string)$export->getContent(), true);
        self::assertIsArray($decoded);
        self::assertSame('Утренний свет', $decoded[0]['title']);
        self::assertSame('system.day', $decoded[0]['event_name']);
        self::assertFalse($decoded[0]['use_core']);

        $importJson = json_encode([
            ['title' => 'Импортированный', 'code' => 'echo 1;', 'description' => 'из JSON', 'event_name' => 'module.demo.ready'],
            ['title' => 'Утренний свет', 'code' => 'дубликат'],
            ['title' => 'Сломанный', 'code' => 'if (', 'description' => 'ошибка'],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $import = $app->handle(Request::create('/scripts/import', 'POST', [
            '_token' => $session->csrfToken(),
            'payload' => $importJson,
        ]));
        self::assertSame(302, $import->getStatusCode());
        self::assertNotNull($scripts->findByTitle('Импортированный'));
        self::assertSame('module.demo.ready', (string)$scripts->findByTitle('Импортированный')['event_name']);
        // Поле отсутствует в JSON — по умолчанию ядро подключено.
        self::assertTrue($scripts->findByTitle('Импортированный')['use_core']);
        self::assertNull($scripts->findByTitle('Сломанный'));
        self::assertSame(2, (int)$app->database()->connection()->table('scripts')->whereNull('deleted_at')->count());
        $location = (string)$import->headers->get('Location');
        self::assertSame('/scripts', $location);
        $result = (string)$app->handle(Request::create($location, 'GET'))->getContent();
        self::assertStringContainsString('Пропущены (уже есть): Утренний свет', $result);
        self::assertStringContainsString('Не сохранены: Сломанный', $result);

        $bad = $app->handle(Request::create('/scripts/import', 'POST', [
            '_token' => $session->csrfToken(),
            'payload' => 'не json',
        ]));
        self::assertSame(302, $bad->getStatusCode());
        self::assertSame('/scripts/import', $bad->headers->get('Location'));
    }

    /**
     * @param array<string, string> $request
     */
    private function yoyo(Application $app, ArraySession $session, array $request): string
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

        $this->browserEventText = (string)json_encode(json_decode((string)$response->headers->get('Yoyo-Browser-Event'), true), JSON_UNESCAPED_UNICODE);
        Yoyo::request()->reset();

        return (string)$response->getContent();
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
            $table->boolean('use_core')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        SessionFixture::authenticate($app);

        return [$app, $session];
    }
}
