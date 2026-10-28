<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Foundation\Config;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Settings\DatabaseSettingsGuard;
use MajorDoMo\Settings\EnvFile;
use MajorDoMo\Settings\SettingsSchema;
use MajorDoMo\Settings\SettingsService;
use MajorDoMo\Settings\SettingsValidationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Настройки окружения: реальная запись только во временный файл, БД — SQLite.
 *
 * 2026-10-28 — результаты действий проверяются в событии Notify.
 * 2026-10-27 — проверяется UI .env вместо CRUD таблицы settings.
 */
final class CoreSettingsTest extends TestCase
{
    private string $directory = '';
    private string $original = '';

    /** @var array<string, string|false> */
    private array $environment = [];

    /** @var array<string, mixed> */
    private array $savedRequest = [];

    /** @var array<string, mixed> */
    private array $savedServer = [];

    protected function setUp(): void
    {
        $this->savedRequest = $_REQUEST;
        $this->savedServer = $_SERVER;
        $_REQUEST = [];
        Yoyo::request()->reset();
        foreach (SettingsSchema::fields() as $key => $field) {
            $this->environment[$key] = getenv($key);
            putenv($key);
        }
        $this->directory = sys_get_temp_dir() . '/mydomo-settings-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        $this->original = "# локальная конфигурация\nAPP_TIMEZONE=Europe/Moscow\nAPP_DEBUG=false\nDB_HOST=localhost\nDB_PORT=3306\nDB_NAME=mydomo\nDB_USER=mydomo\nDB_PASSWORD='fixture-db-secret'\nREDIS_PASSWORD='fixture-redis-secret'\nCUSTOM_KEY=keep-me\n";
        file_put_contents($this->path(), $this->original);
    }

    protected function tearDown(): void
    {
        foreach ($this->environment as $key => $value) {
            putenv($value === false ? $key : $key . '=' . $value);
        }
        $_REQUEST = $this->savedRequest;
        $_SERVER = $this->savedServer;
        Yoyo::request()->reset();
        foreach (['.env', '.env.lock'] as $file) {
            if (is_file($this->directory . '/' . $file)) {
                unlink($this->directory . '/' . $file);
            }
        }
        Config::boot($this->directory);
        rmdir($this->directory);
    }

    public function testPageShowsSchemaWithoutSettingsTable(): void
    {
        [$app] = $this->boot();
        $page = $app->handle(Request::create('/settings', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        $html = (string)$page->getContent();
        self::assertStringContainsString('yoyo:name="system-settings"', $html);
        self::assertStringContainsString('APP_TIMEZONE', $html);
        self::assertStringContainsString('/settings?group=retention', $html);
        self::assertFalse($app->database()->schema()->hasTable('settings'));
    }

    public function testSaveThroughYoyoChangesEnvAndNextBootstrapConfiguration(): void
    {
        [$app] = $this->boot();
        $response = $this->yoyo($app, 'save', [
            'group' => 'general', 'revision' => $this->revision(),
            'values' => ['APP_TIMEZONE' => 'UTC', 'APP_DEBUG' => '1'],
        ]);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Настройки сохранены', (string)json_encode(json_decode((string)$response->headers->get('Yoyo-Browser-Event'), true), JSON_UNESCAPED_UNICODE));
        $contents = (string)file_get_contents($this->path());
        self::assertStringContainsString('# локальная конфигурация', $contents);
        self::assertStringContainsString('CUSTOM_KEY=keep-me', $contents);
        self::assertStringContainsString("DB_PASSWORD='fixture-db-secret'", $contents);
        Config::boot($this->directory);
        $config = SettingsSchema::environment(dirname(__DIR__, 2));
        self::assertSame('UTC', $config['timezone']);
        self::assertTrue($config['debug']);
    }

    public function testUnsupportedTimezoneIsRejectedWithoutWriting(): void
    {
        [$app] = $this->boot();
        $response = $this->yoyo($app, 'save', [
            'group' => 'general', 'revision' => $this->revision(),
            'values' => ['APP_TIMEZONE' => 'Invented/Zone', 'APP_DEBUG' => '1'],
        ]);
        self::assertStringContainsString('aria-invalid="true"', (string)$response->getContent());
        self::assertStringContainsString('поддерживаемых вариантов', (string)$response->getContent());
        self::assertSame($this->original, file_get_contents($this->path()));
    }

    public function testSearchFindsDescriptionsAndLinksToField(): void
    {
        [$app] = $this->boot();
        $response = $this->yoyo($app, 'findSettings', ['q' => 'таймеры']);
        self::assertStringContainsString('/settings?group=retention#field-RETENTION_TIMEOUTS_DAYS', (string)$response->getContent());
        self::assertStringNotContainsString('fixture-db-secret', (string)$response->getContent());
    }

    public function testSecretsAreAbsentFromHtmlAndSerializedState(): void
    {
        [$app] = $this->boot();
        foreach (['database', 'cache'] as $group) {
            $response = $app->handle(Request::create('/settings?group=' . $group, 'GET'));
            $html = (string)$response->getContent();
            self::assertSame(200, $response->getStatusCode());
            self::assertStringNotContainsString('fixture-db-secret', $html);
            self::assertStringNotContainsString('fixture-redis-secret', $html);
            self::assertStringContainsString('Пароль сохранён', $html);
        }
    }

    public function testFailedPostDoesNotEchoSubmittedPassword(): void
    {
        [$app] = $this->boot();
        $response = $this->yoyo($app, 'save', [
            'group' => 'database', 'revision' => $this->revision(),
            'values' => ['DB_HOST' => 'localhost', 'DB_PORT' => 'invalid', 'DB_NAME' => 'mydomo', 'DB_USER' => 'mydomo', 'DB_PASSWORD' => 'new-secret'],
        ]);
        self::assertStringNotContainsString('new-secret', (string)$response->getContent());
        self::assertSame($this->original, file_get_contents($this->path()));
    }

    public function testGetCannotInvokeSave(): void
    {
        [$app] = $this->boot();
        $response = $this->yoyo($app, 'save', [
            'group' => 'general', 'revision' => $this->revision(),
            'values' => ['APP_TIMEZONE' => 'UTC', 'APP_DEBUG' => '1'],
        ], 'GET');
        self::assertStringContainsString('только через POST', (string)json_encode(json_decode((string)$response->headers->get('Yoyo-Browser-Event'), true), JSON_UNESCAPED_UNICODE));
        self::assertSame($this->original, file_get_contents($this->path()));
    }

    public function testPostRequiresCsrf(): void
    {
        [$app] = $this->boot();
        $response = $this->yoyo($app, 'save', [
            'group' => 'general', 'revision' => $this->revision(),
            'values' => ['APP_TIMEZONE' => 'UTC', 'APP_DEBUG' => '1'],
        ], 'POST', false);
        self::assertSame(403, $response->getStatusCode());
        self::assertSame($this->original, file_get_contents($this->path()));
    }

    public function testAnonymousCannotReadOrWriteSettings(): void
    {
        [$app, $session] = $this->boot();
        $session->logout();
        $response = $app->handle(Request::create('/settings', 'GET'));
        self::assertSame(302, $response->getStatusCode());
        $save = $this->yoyo($app, 'save', ['group' => 'general'], 'POST');
        self::assertSame(401, $save->getStatusCode());
        self::assertSame($this->original, file_get_contents($this->path()));
    }

    public function testUnknownKeysCannotBeInjected(): void
    {
        $this->expectException(RuntimeException::class);
        try {
            $this->service()->save('general', ['APP_TIMEZONE' => 'UTC', 'APP_DEBUG' => '0', 'DB_HOST' => 'evil'], [], $this->revision(), []);
        } finally {
            self::assertSame($this->original, file_get_contents($this->path()));
        }
    }

    public function testStaleRevisionDoesNotOverwriteOtherEdits(): void
    {
        $revision = $this->revision();
        $new = $this->original . "ANOTHER_KEY=added\n";
        file_put_contents($this->path(), $new);
        $this->expectException(RuntimeException::class);
        try {
            $this->service()->save('general', ['APP_TIMEZONE' => 'UTC', 'APP_DEBUG' => '0'], [], $revision, []);
        } finally {
            self::assertSame($new, file_get_contents($this->path()));
        }
    }

    public function testProcessEnvironmentIsReadOnly(): void
    {
        putenv('APP_TIMEZONE=Asia/Tokyo');
        $service = $this->service();
        self::assertSame('Asia/Tokyo', $service->snapshot()['fields']['APP_TIMEZONE']['value']);
        self::assertTrue($service->snapshot()['fields']['APP_TIMEZONE']['external']);
        $this->expectException(SettingsValidationException::class);
        $service->save('general', ['APP_TIMEZONE' => 'UTC', 'APP_DEBUG' => '0'], [], $this->revision(), []);
    }

    public function testEmptyPasswordKeepsSecretAndExplicitClearRemovesIt(): void
    {
        $service = $this->service();
        $values = $this->cacheInput();
        $values['REDIS_PASSWORD'] = '';
        $service->save('cache', $values, [], $this->revision(), []);
        self::assertSame('fixture-redis-secret', Config::parse((string)file_get_contents($this->path()))['REDIS_PASSWORD']);
        $service->save('cache', $values, ['REDIS_PASSWORD' => '1'], $this->revision(), []);
        self::assertSame('', Config::parse((string)file_get_contents($this->path()))['REDIS_PASSWORD']);
    }

    public function testInvalidCacheDriverAndPortAreRejected(): void
    {
        $values = $this->cacheInput();
        $values['CACHE_DRIVER'] = 'memcached';
        $values['REDIS_PORT'] = '65536';
        try {
            $this->service()->save('cache', $values, [], $this->revision(), []);
            self::fail('Неподдерживаемый кэш не должен сохраняться.');
        } catch (SettingsValidationException $error) {
            self::assertArrayHasKey('CACHE_DRIVER', $error->errors);
            self::assertArrayHasKey('REDIS_PORT', $error->errors);
        }
        self::assertSame($this->original, file_get_contents($this->path()));
    }

    public function testQueueLeaseMustExceedHandlerTimeout(): void
    {
        $this->expectException(SettingsValidationException::class);
        $this->service()->save('queue', [
            'QUEUE_MAX_ATTEMPTS' => '3', 'QUEUE_RETRY_DELAY' => '60',
            'QUEUE_RESERVATION_TIMEOUT' => '5', 'QUEUE_HANDLER_TIMEOUT' => '5',
        ], [], $this->revision(), []);
    }

    public function testRuntimeReceivesQueueLoggingAndRetentionValues(): void
    {
        file_put_contents($this->path(), "QUEUE_MAX_ATTEMPTS=7\nLOG_LEVEL=warning\nRETENTION_HISTORY_DAYS=0\nRETENTION_INTERVAL=600\n");
        Config::boot($this->directory);
        $config = SettingsSchema::environment(dirname(__DIR__, 2));
        self::assertSame(7, $config['queue']['max_attempts']);
        self::assertSame('warning', $config['logging']['level']);
        self::assertSame(0, $config['retention']['history_days']);
        self::assertSame(600, $config['retention']['interval']);
        self::assertSame(4, (new Application(dirname(__DIR__, 2), $config))->config('queue.events_lanes'));
    }

    public function testDatabaseChangeIsVerifiedBeforeWriting(): void
    {
        $guard = $this->createMock(DatabaseSettingsGuard::class);
        $guard->expects(self::once())->method('verify')->with(
            self::callback(static fn (array $values): bool => $values['DB_HOST'] === 'new-db' && $values['DB_PASSWORD'] === 'fixture-db-secret'),
            ['id' => 1]
        );
        $this->service($guard)->save('database', [
            'DB_HOST' => 'new-db', 'DB_PORT' => '3306', 'DB_NAME' => 'mydomo', 'DB_USER' => 'mydomo', 'DB_PASSWORD' => '',
        ], [], $this->revision(), ['id' => 1]);
        self::assertSame('new-db', Config::parse((string)file_get_contents($this->path()))['DB_HOST']);
    }

    public function testFailedDatabaseProbeLeavesEnvUntouched(): void
    {
        $guard = $this->createMock(DatabaseSettingsGuard::class);
        $guard->method('verify')->willThrowException(new RuntimeException('Проверка подключения не пройдена.'));
        $this->expectException(RuntimeException::class);
        try {
            $this->service($guard)->save('database', [
                'DB_HOST' => 'new-db', 'DB_PORT' => '3306', 'DB_NAME' => 'mydomo', 'DB_USER' => 'mydomo',
            ], [], $this->revision(), []);
        } finally {
            self::assertSame($this->original, file_get_contents($this->path()));
        }
    }

    public function testAtomicWriterRoundTripsQuotesAndUpdatesDuplicateKeys(): void
    {
        file_put_contents($this->path(), "# keep\r\nexport REDIS_PASSWORD=old\r\nCUSTOM_KEY=keep\r\nREDIS_PASSWORD=second\r\n");
        $value = ' "quoted" \'single\' #hash = value \\tail ';
        (new EnvFile($this->path()))->save(['REDIS_PASSWORD' => $value], $this->revision());
        $contents = (string)file_get_contents($this->path());
        self::assertSame($value, Config::parse($contents)['REDIS_PASSWORD']);
        self::assertStringContainsString("# keep\r\n", $contents);
        self::assertStringContainsString("CUSTOM_KEY=keep\r\n", $contents);
        self::assertStringNotContainsString('second', $contents);
        self::assertSame([], glob($this->directory . '/.env-write-*'));
    }

    public function testWriterRejectsNewlineInjectionBeforeReplacingFile(): void
    {
        $this->expectException(RuntimeException::class);
        try {
            (new EnvFile($this->path()))->save(['REDIS_PASSWORD' => "secret\nAPP_DEBUG=1"], $this->revision());
        } finally {
            self::assertSame($this->original, file_get_contents($this->path()));
        }
    }

    /** @return array<string, string> */
    private function cacheInput(): array
    {
        return ['CACHE_DRIVER' => 'file', 'REDIS_HOST' => '127.0.0.1', 'REDIS_PORT' => '6379', 'REDIS_DB' => '0'];
    }

    private function service(?DatabaseSettingsGuard $guard = null): SettingsService
    {
        $guard ??= $this->createMock(DatabaseSettingsGuard::class);
        return new SettingsService(new EnvFile($this->path()), SettingsSchema::defaults(dirname(__DIR__, 2)), $guard);
    }

    /** @return array{0:Application,1:ArraySession} */
    private function boot(): array
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true, 'environment_file' => $this->path(),
            'cache' => ['driver' => 'array'], 'log' => 'php://stderr', 'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        SessionFixture::authenticate($app);
        $app->container()->instance(DatabaseSettingsGuard::class, $this->createMock(DatabaseSettingsGuard::class));
        return [$app, $session];
    }

    /** @param array<string, mixed> $data */
    private function yoyo(Application $app, string $action, array $data, string $method = 'POST', bool $csrf = true): \Symfony\Component\HttpFoundation\Response
    {
        Yoyo::request()->reset();
        $_REQUEST = $data + ['component' => 'system-settings/' . $action, 'yoyo-id' => 'test-system-settings'];
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        Yoyo::request()->mock($_REQUEST, $_SERVER);
        return $app->handle(Request::create('/yoyo', $method, [], [], [], $csrf ? ['HTTP_X_CSRF_TOKEN' => $app->session()->csrfToken()] : []));
    }

    private function path(): string
    {
        return $this->directory . '/.env';
    }

    private function revision(): string
    {
        return hash('sha256', (string)file_get_contents($this->path()));
    }
}
