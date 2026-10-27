<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Api\Webhooks\WebhookException;
use MajorDoMo\Api\Webhooks\WebhookRepository;
use MajorDoMo\Api\Webhooks\WebhookService;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Runtime\CodeRunner;
use MajorDoMo\Runtime\PhpSyntaxValidator;
use MajorDoMo\Scripts\ScriptRepository;
use MajorDoMo\Scripts\ScriptRunner;
use PHPUnit\Framework\TestCase;

/**
 * Входящие webhook: секрет, методы, сценарий/PHP, HTTP-вход и админка.
 *
 * 2026-10-27 — PHP-код webhook проверяется синтаксически до сохранения.
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 */
final class ApiWebhooksTest extends TestCase
{
    public function testCodeHandlerAndMethodChecks(): void
    {
        [$app, $service] = $this->boot();
        $created = $service->create([
            'name' => 'Датчик',
            'handler_type' => 'code',
            'code' => 'return ["x" => $params["payload"]["x"] ?? null, "method" => $params["method"]];',
            'methods' => ['POST'],
            'enabled' => true,
        ]);
        self::assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $created['secret']);

        $result = $service->handle($created['secret'], 'POST', ['x' => 7]);
        self::assertSame(['x' => 7, 'method' => 'POST'], $result);

        try {
            $service->handle($created['secret'], 'GET', []);
            self::fail('Ожидался 405');
        } catch (WebhookException $error) {
            self::assertSame(405, $error->status);
            self::assertContains('POST', $error->allow);
        }

        $unknown = str_repeat('a', 48);
        try {
            $service->handle($unknown, 'POST', []);
            self::fail('Ожидался 404');
        } catch (WebhookException $error) {
            self::assertSame(404, $error->status);
        }

        $service->update($created['id'], [
            'name' => 'Датчик',
            'handler_type' => 'code',
            'code' => 'return 1;',
            'methods' => ['POST'],
            'enabled' => false,
        ]);
        try {
            $service->handle($created['secret'], 'POST', []);
            self::fail('Ожидался 403');
        } catch (WebhookException $error) {
            self::assertSame(403, $error->status);
        }
    }

    public function testScriptHandlerAndRotation(): void
    {
        [$app, $service] = $this->boot();
        $app->database()->connection()->table('scripts')->insert([
            'title' => 'Webhook double',
            'code' => 'return ((int)($params["payload"]["n"] ?? 0)) * 2;',
            'description' => '',
            'category_id' => 0,
            'event_name' => '',
            'created_at' => '2026-10-26 12:00:00',
            'updated_at' => '2026-10-26 12:00:00',
        ]);
        $created = $service->create([
            'name' => 'Сценарий',
            'handler_type' => 'script',
            'script_id' => 1,
            'methods' => ['POST', 'PUT'],
            'enabled' => true,
        ]);
        self::assertSame(6, $service->handle($created['secret'], 'POST', ['n' => 3]));
        self::assertSame(8, $service->handle($created['secret'], 'PUT', ['n' => 4]));

        $newSecret = $service->rotate($created['id']);
        self::assertNotSame($created['secret'], $newSecret);
        try {
            $service->handle($created['secret'], 'POST', ['n' => 1]);
            self::fail('Старый секрет должен быть отклонён');
        } catch (WebhookException $error) {
            self::assertSame(404, $error->status);
        }
        self::assertSame(10, $service->handle($newSecret, 'POST', ['n' => 5]));
    }

    public function testPublicEndpointAndAdminPage(): void
    {
        [$app, $service] = $this->boot(true);
        $created = $service->create([
            'name' => 'HTTP вход',
            'handler_type' => 'code',
            'code' => 'return ["ok" => true];',
            'methods' => ['POST'],
            'enabled' => true,
        ]);

        $response = $app->handle(Request::create('/api/v1/webhooks/' . $created['secret'], 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['hello' => 'world'], JSON_THROW_ON_ERROR)));
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue((bool)(json_decode((string)$response->getContent(), true)['ok'] ?? false));

        $page = $app->handle(Request::create('/api', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        $html = (string)$page->getContent();
        self::assertStringContainsString('Входящие webhook', $html);
        self::assertStringContainsString('HTTP вход', $html);
    }

    public function testAdminCreateRequiresCsrfAndShowsSecretOnce(): void
    {
        [$app, $service] = $this->boot(true);
        $session = $this->session;

        $denied = $app->handle(Request::create('/api', 'POST', ['name' => 'Без токена', 'handler_type' => 'code', 'code' => 'return 1;']));
        self::assertSame(403, $denied->getStatusCode());

        $response = $app->handle(Request::create('/api', 'POST', [
            '_token' => $session->csrfToken(),
            'name' => 'Из админки',
            'handler_type' => 'code',
            'code' => 'return 1;',
            'methods' => ['POST'],
            'enabled' => '1',
        ]));
        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('Секрет создан', $html);
        self::assertMatchesRegularExpression('#/api/v1/webhooks/[a-f0-9]{48}#', $html);
    }

    private ArraySession $session;

    /**
     * @return array{0: Application, 1: WebhookService}
     */
    private function boot(bool $login = false): array
    {
        $this->session = new ArraySession();
        if ($login) {
            $this->session->login(['id' => 1, 'username' => 'admin', 'name' => 'Администратор', 'is_admin' => true]);
        }
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'cache' => ['driver' => 'array'],
            'log' => 'php://stderr',
            'session' => $this->session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();

        $schema = $app->database()->schema();
        $schema->create('api_webhooks', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name', 190);
            $table->string('secret_hash', 64)->unique();
            $table->string('secret_prefix', 12);
            $table->string('handler_type', 16)->default('script');
            $table->integer('script_id')->nullable();
            $table->text('code')->nullable();
            $table->string('methods', 100)->default('["POST"]');
            $table->boolean('enabled')->default(true);
            $table->dateTime('last_used_at')->nullable();
            $table->integer('last_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('scripts', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title', 191);
            $table->text('code')->nullable();
            $table->text('description')->nullable();
            $table->integer('category_id')->default(0);
            $table->string('event_name', 191)->default('');
            $table->timestamps();
            $table->softDeletes();
        });

        $service = new WebhookService(
            new WebhookRepository($app->database()),
            $app->container()->make(ScriptRepository::class),
            $app->container()->make(ScriptRunner::class),
            new CodeRunner(),
            new PhpSyntaxValidator()
        );

        SessionFixture::authenticate($app);

        return [$app, $service];
    }
}
