<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\Controllers\InstallController;
use LaIOT\Http\Session\ArraySession;
use LaIOT\Services\Install\InstallReport;
use LaIOT\Services\Install\InstallService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2026-10-28 — пошаговый мастер: ключ, CSRF, шаги и закрытие после установки.
 * 2026-10-29 — реквизиты от SSH-мастера не раскрываются без ключа и не попадают в HTML.
 */
final class InstallPageTest extends TestCase
{
    private Application $app;
    private ArraySession $session;
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/laiot-install-page-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/var', 0775, true);
        $this->session = new ArraySession();
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $this->session,
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $this->app->boot();
        $this->app->container()->instance(InstallService::class, new InstallService(
            $this->app,
            $this->root,
            fn (array $config) => $this->app->database(),
            fn (array $config) => $this->app,
            dirname(__DIR__, 2) . '/database/migrations'
        ));
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testPageShowsKeyFormAndCreatesKey(): void
    {
        $response = $this->get('/install');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Остался ключ', (string)$response->getContent());
        self::assertStringContainsString('var/install.key', (string)$response->getContent());
        self::assertFileExists($this->root . '/var/install.key');
        self::assertFileDoesNotExist($this->root . '/.env');
    }

    public function testPageWithValidKeyShowsWelcome(): void
    {
        $service = $this->app->container()->make(InstallService::class);
        $key = (string)$service->key();

        $response = $this->get('/install?key=' . $key);

        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('Привет! Давай оживим дом', $html);
        self::assertStringContainsString('Начать настройку', $html);
        self::assertStringContainsString('name="_token"', $html);
    }

    public function testSshConnectionIsLoadedAfterKeyWithoutPasswordInHtml(): void
    {
        $service = $this->app->container()->make(InstallService::class);
        $key = (string)$service->key();
        file_put_contents($this->root . '/var/install-connection.json', json_encode([
            'db_name' => 'prepared_db', 'db_user' => 'prepared_user', 'db_password' => 'Private-Connection-42!',
            'db_create' => false, 'cache_driver' => 'redis',
        ], JSON_THROW_ON_ERROR));
        $this->get('/install');
        self::assertNull($this->session->get(InstallController::DRAFT_KEY));
        $wrong = $this->get('/install?key=incorrect');
        self::assertSame(403, $wrong->getStatusCode());
        self::assertNull($this->session->get(InstallController::DRAFT_KEY));
        $response = $this->get('/install?key=' . $key . '&step=3');
        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('prepared_db', $html);
        self::assertStringContainsString('prepared_user', $html);
        self::assertStringNotContainsString('Private-Connection-42!', $html);
        self::assertSame('Private-Connection-42!', $this->session->get(InstallController::DRAFT_KEY)['db_password']);
        self::assertFileDoesNotExist($this->root . '/.env');
    }

    public function testChecksStepShowsFriendlyStatuses(): void
    {
        $service = $this->app->container()->make(InstallService::class);
        $key = (string)$service->key();

        $response = $this->get('/install?key=' . $key . '&step=2');

        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('Проверим сервер', $html);
        self::assertStringContainsString('Фоновые службы', $html);
        self::assertStringContainsString('Продолжить', $html);
    }

    public function testWrongKeyIsForbidden(): void
    {
        $this->app->container()->make(InstallService::class)->key();

        $response = $this->get('/install?key=' . str_repeat('a', 64));

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('Ключ не подошёл', (string)$response->getContent());
    }

    public function testInstalledPageIsNotFound(): void
    {
        file_put_contents($this->root . '/var/installed.lock', '{}');

        self::assertSame(404, $this->get('/install')->getStatusCode());
    }

    public function testPostWithoutCsrfIsRejected(): void
    {
        $service = $this->app->container()->make(InstallService::class);
        $key = (string)$service->key();

        $response = $this->post('/install', ['key' => $key]);

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('csrf_failed', (string)$response->getContent());
    }

    public function testPostWithWrongKeyIsForbidden(): void
    {
        $this->app->container()->make(InstallService::class)->key();

        $response = $this->post('/install', [
            '_token' => $this->session->csrfToken(),
            'key' => str_repeat('b', 64),
        ]);

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('Ключ не подошёл', (string)$response->getContent());
    }

    public function testConnectionStepShowsFieldErrorsAndWritesNothing(): void
    {
        $service = $this->app->container()->make(InstallService::class);
        $key = (string)$service->key();

        $response = $this->post('/install', [
            '_token' => $this->session->csrfToken(),
            'key' => $key,
            'stage' => 'connection',
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_name' => 'bad name',
            'db_user' => '',
            'db_password' => 'secret-db',
            'db_create' => '1',
            'cache_driver' => 'file',
        ]);

        self::assertSame(422, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('Имя базы: латиница', $html);
        self::assertStringContainsString('Укажи пользователя базы', $html);
        self::assertFileDoesNotExist($this->root . '/.env');
    }

    public function testAdminStepShowsFieldErrorsAndWritesNothing(): void
    {
        $service = $this->app->container()->make(InstallService::class);
        $key = (string)$service->key();
        $this->session->set(InstallController::DRAFT_KEY, [
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_name' => 'laiot',
            'db_user' => 'laiot',
            'db_password' => 'secret-db',
            'db_create' => true,
            'cache_driver' => 'file',
            'redis_host' => '127.0.0.1',
            'redis_port' => 6379,
            'redis_database' => 0,
            'redis_password' => '',
        ]);

        $response = $this->post('/install', [
            '_token' => $this->session->csrfToken(),
            'key' => $key,
            'stage' => 'install',
            'admin_login' => 'ab',
            'admin_name' => 'Администратор',
            'admin_password' => 'short',
            'admin_password_confirm' => 'other',
            'timezone' => 'Europe/Moscow',
        ]);

        self::assertSame(422, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('Логин: 8–64 символа', $html);
        self::assertStringContainsString('Пароли не совпадают', $html);
        self::assertFileDoesNotExist($this->root . '/.env');
    }

    public function testInstallWithoutDraftReturnsToConnectionStep(): void
    {
        $service = $this->app->container()->make(InstallService::class);
        $key = (string)$service->key();

        $response = $this->post('/install', [
            '_token' => $this->session->csrfToken(),
            'key' => $key,
            'stage' => 'install',
            'admin_login' => 'sysadmin',
            'admin_name' => 'Администратор',
            'admin_password' => 'secret!123',
            'admin_password_confirm' => 'secret!123',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('Сначала подключим базу', (string)$response->getContent());
    }

    public function testDonePageRendersReport(): void
    {
        $report = new InstallReport(
            version: '2.0.0-test',
            adminId: 1,
            adminLogin: 'sysadmin',
            migrations: ['a.php', 'b.php'],
            installedAt: '2026-10-28 23:00:00',
            cacheDriver: 'redis',
            warnings: ['Службы не запущены.']
        );

        $html = $this->app->container()->make(Factory::class)->make('install.page', [
            'state' => 'done',
            'version' => '2.0.0-test',
            'report' => $report,
            'login' => '/login',
        ])->render();

        self::assertStringContainsString('Дом готов!', $html);
        self::assertStringContainsString('sysadmin', $html);
        self::assertStringContainsString('Службы не запущены.', $html);
        self::assertStringContainsString('href="/login"', $html);
    }

    private function get(string $uri): Response
    {
        return $this->app->handle(Request::create($uri, 'GET'));
    }

    /**
     * @param array<string, string> $data
     */
    private function post(string $uri, array $data): Response
    {
        return $this->app->handle(Request::create($uri, 'POST', $data));
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
