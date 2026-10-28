<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\Auth\UserRepository;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Foundation\Config;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Install\InstallException;
use MajorDoMo\Install\InstallRequest;
use MajorDoMo\Install\InstallService;
use MajorDoMo\Security\EncryptionKey;
use PHPUnit\Framework\TestCase;

/**
 * 2026-10-28 — установщик проверяется на изолированной SQLite-схеме.
 * 2026-10-28 — APP_KEY создаётся автоматически и сохраняется после сбоя.
 */
final class InstallServiceTest extends TestCase
{
    private Application $app;
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mydomo-install-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0775, true);

        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => new ArraySession(),
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $this->app->boot();
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testKeyIsCreatedOnceAndCompared(): void
    {
        $service = $this->service();

        $key = $service->key();
        self::assertNotNull($key);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $key);
        self::assertSame($key, $service->key());
        self::assertSame($key, trim((string)file_get_contents($this->root . '/var/install.key')));
        self::assertTrue($service->tokenMatches($key));
        self::assertFalse($service->tokenMatches(substr($key, 0, 32)));
        self::assertFalse($service->tokenMatches(''));
    }

    public function testApplyWritesEnvironmentMigratesAndCreatesAdmin(): void
    {
        $service = $this->service();
        self::assertFalse($service->installed());

        $report = $service->apply($this->request());

        self::assertSame('sysadmin', $report->adminLogin);
        self::assertGreaterThan(0, $report->adminId);
        self::assertGreaterThan(0, $report->migrationsCount());
        self::assertFileExists($this->root . '/var/installed.lock');
        self::assertFileExists($this->root . '/.env');

        $env = (string)file_get_contents($this->root . '/.env');
        self::assertStringContainsString('DB_NAME="mydomo"', $env);
        self::assertStringContainsString('DB_USER="mydomo"', $env);
        self::assertStringContainsString('DB_PASSWORD="secret-db"', $env);
        self::assertStringContainsString('CACHE_DRIVER="file"', $env);
        self::assertStringContainsString('APP_TIMEZONE="Europe/Moscow"', $env);
        self::assertStringNotContainsString('REDIS_HOST', $env);
        $key = Config::parse($env)['APP_KEY'] ?? '';
        self::assertSame(32, strlen(EncryptionKey::decode($key)));

        $user = $this->app->container()->make(UserRepository::class)->verifyAdmin('sysadmin', 'secret!123');
        self::assertNotNull($user);
        self::assertTrue($service->installed());
    }

    public function testInstalledSurvivesLostLockWhenAdminExists(): void
    {
        $service = $this->service();
        $service->apply($this->request());
        unlink($this->root . '/var/installed.lock');

        self::assertTrue($service->installed());
    }

    public function testExistingApplicationKeyIsPreserved(): void
    {
        $key = EncryptionKey::generate();
        file_put_contents($this->root . '/.env', "APP_KEY=\"" . $key . "\"\n# retained\n");
        $this->service()->apply($this->request());
        $contents = (string)file_get_contents($this->root . '/.env');
        self::assertTrue($key === (Config::parse($contents)['APP_KEY'] ?? null));
        self::assertStringContainsString('# retained', $contents);
    }

    public function testRetryAfterFailureKeepsGeneratedApplicationKey(): void
    {
        $attempt = 0;
        $service = new InstallService(
            $this->app,
            $this->root,
            fn (array $config) => $this->app->database(),
            function (array $config) use (&$attempt): Application {
                if (++$attempt === 1) {
                    throw new InstallException('Тестовый сбой до создания администратора.');
                }

                return $this->app;
            },
            dirname(__DIR__, 2) . '/database/migrations'
        );
        try {
            $service->apply($this->request());
            self::fail('Ожидался тестовый сбой.');
        } catch (InstallException $error) {
            self::assertStringContainsString('Тестовый сбой', $error->getMessage());
        }
        $key = Config::parse((string)file_get_contents($this->root . '/.env'))['APP_KEY'];
        self::assertSame(32, strlen(EncryptionKey::decode($key)));
        $service->apply($this->request());
        self::assertTrue($key === Config::parse((string)file_get_contents($this->root . '/.env'))['APP_KEY']);
    }

    public function testSecondApplyIsRejected(): void
    {
        $service = $this->service();
        $service->apply($this->request());

        $this->expectException(InstallException::class);
        $service->apply($this->request());
    }

    public function testValidationReportsEveryField(): void
    {
        $errors = $this->service()->validate(new InstallRequest(
            dbName: 'bad-name',
            dbUser: '',
            dbCreate: true,
            adminLogin: 'ab',
            adminName: '',
            adminPassword: 'short',
            timezone: 'Nowhere/Invalid'
        ));

        self::assertArrayHasKey('db_name', $errors);
        self::assertArrayHasKey('db_user', $errors);
        self::assertArrayHasKey('admin_login', $errors);
        self::assertArrayHasKey('admin_name', $errors);
        self::assertArrayHasKey('admin_password', $errors);
        self::assertArrayHasKey('timezone', $errors);
    }

    public function testPreflightReportsUnreachableDatabase(): void
    {
        $checks = $this->service()->preflight(new InstallRequest(
            dbHost: '127.0.0.1',
            dbPort: 1,
            dbName: 'mydomo',
            dbUser: 'mydomo',
            cacheDriver: 'file'
        ));

        self::assertNotEmpty($checks);
        self::assertSame('db_server', $checks[0]->id);
        self::assertTrue($checks[0]->failed());
    }

    public function testApplyRejectsWeakPasswordBeforeTouchingDatabase(): void
    {
        $service = $this->service();
        $request = $this->request(adminPassword: 'weak');

        try {
            $service->apply($request);
            self::fail('Ожидалась ошибка пароля.');
        } catch (InstallException $error) {
            self::assertSame('admin_password', $error->field());
        }

        self::assertFileDoesNotExist($this->root . '/.env');
        self::assertFalse($service->installed());
    }

    private function request(string $adminPassword = 'secret!123'): InstallRequest
    {
        return new InstallRequest(
            dbHost: '127.0.0.1',
            dbName: 'mydomo',
            dbUser: 'mydomo',
            dbPassword: 'secret-db',
            dbCreate: true,
            cacheDriver: 'file',
            adminLogin: 'sysadmin',
            adminName: 'Администратор',
            adminPassword: $adminPassword,
            timezone: 'Europe/Moscow'
        );
    }

    private function service(): InstallService
    {
        return new InstallService(
            $this->app,
            $this->root,
            fn (array $config) => $this->app->database(),
            fn (array $config) => $this->app,
            dirname(__DIR__, 2) . '/database/migrations'
        );
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                unlink($path);
            }

            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->remove($path . '/' . $item);
        }
        rmdir($path);
    }
}
