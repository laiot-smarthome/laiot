<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use LaIOT\Console\Commands\ReleaseBuildCommand;
use LaIOT\Database\DatabaseManager;
use LaIOT\Foundation\Application;
use LaIOT\Foundation\Config;
use LaIOT\Foundation\ConfigLoader;
use LaIOT\Http\Session\ArraySession;
use LaIOT\Services\Modules\ModuleManager;
use LaIOT\Services\Settings\EnvFile;
use LaIOT\Services\Systemd\ProcessResult;
use LaIOT\Services\Systemd\ProcessRunner;
use LaIOT\Services\Systemd\UpdateAgent;
use LaIOT\Services\Updates\ReleaseCatalog;
use LaIOT\Services\Updates\ReleaseLayout;
use LaIOT\Services\Updates\ReleaseManifest;
use LaIOT\Services\Updates\ReleasePackage;
use LaIOT\Services\Updates\UpdateDatabase;
use LaIOT\Services\Updates\UpdateFiles;
use LaIOT\Services\Updates\UpdateHost;
use LaIOT\Services\Updates\UpdateRunner;
use LaIOT\Services\Updates\UpdateService;
use LaIOT\Services\Updates\UpdateState;
use LaIOT\Support\HelperLoader;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Проверки обновителя работают только во временных каталогах, с SQLite и подменой HTTP/systemd.
 * 2026-10-29 — прерывание миграции, повтор, откат, целостность архива и защита веб-действий.
 */
final class UpdatesTest extends TestCase
{
    private string $directory;
    private UpdateState $state;
    private UpdateFixtureDatabase $database;
    private UpdateFixtureHost $host;
    private UpdateFixtureService $updates;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/laiot-update-test-' . bin2hex(random_bytes(8));
        UpdateFiles::directory($this->directory . '/var');
        foreach (['app/removed.php', 'bin/laiot', 'bootstrap/app.php', 'vendor/autoload.php', 'public/index.php', 'public/assets/admin/laiot.css', 'modules/third/module.json', 'modules/third/database.sqlite', 'var/files/keep.txt', '.env'] as $file) {
            UpdateFiles::write($this->directory . '/' . $file, 'original:' . $file);
        }
        $this->state = new UpdateState($this->directory . '/var/updates');
        $this->database = new UpdateFixtureDatabase();
        $this->host = new UpdateFixtureHost($this->database);
        $this->updates = new UpdateFixtureService(
            new ReleaseLayout($this->directory),
            $this->state,
            new ReleaseCatalog(new MockHttpClient(), 'laiot-smarthome/laiot'),
            $this->database,
            $this->host,
            ['version' => '2.0.0', 'updates' => ['channel' => 'beta']]
        );
    }

    protected function tearDown(): void
    {
        Yoyo::request()->reset();
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_HX_REQUEST']);
        $_REQUEST = [];
        $this->remove($this->directory);
    }

    public function testInterruptedStateUsesLockRatherThanStalePid(): void
    {
        UpdateFiles::json($this->state->directory . '/operation.json', ['status' => 'running', 'heartbeat' => time() - 60]);
        self::assertSame('interrupted', $this->state->read()['status']);
        $lock = $this->state->lock('run');
        self::assertSame('running', $this->state->read()['status']);
        fclose($lock);
        self::assertSame('interrupted', $this->state->read()['status']);
    }

    public function testAtomicJournalKeepsPreviousFields(): void
    {
        $this->state->save(['source' => 'beta-2.0.0', 'snapshot_ready' => true]);
        $this->state->save(['phase' => 'database']);
        self::assertTrue($this->state->raw()['snapshot_ready']);
        self::assertSame('beta-2.0.0', $this->state->raw()['source']);
        self::assertSame([], glob($this->state->directory . '/*.tmp'));
    }

    public function testForkUrlIsNormalizedAndRemoteAssetsCannotEscapeRepository(): void
    {
        $payload = [['draft' => false, 'tag_name' => 'beta-2.1.0', 'name' => 'release', 'assets' => [
            ['name' => 'laiot.zip', 'browser_download_url' => 'https://github.com/evil/repo/releases/download/beta-2.1.0/laiot.zip'],
            ['name' => 'laiot-release.json', 'browser_download_url' => 'https://github.com/example/fork/releases/download/beta-2.1.0/laiot-release.json'],
        ]]];
        $catalog = new ReleaseCatalog(new MockHttpClient([new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR))]), 'https://github.com/example/fork.git');
        self::assertSame('example/fork', $catalog->repository);
        $this->expectException(RuntimeException::class);
        $catalog->releases();
    }

    public function testCatalogIgnoresDraftsAndUnnumberedReleases(): void
    {
        $catalog = new ReleaseCatalog(new MockHttpClient([new MockResponse('[{"draft":true},{"draft":false,"tag_name":"latest"}]')]), 'laiot-smarthome/laiot');
        self::assertSame([], $catalog->releases());
    }

    public function testArchiveCannotOverwriteEnvironmentOrThirdPartyCode(): void
    {
        foreach (['../outside.php', '.env', 'var/files/keep.txt', 'modules/third/module.json', 'public/../../secret', 'public\\evil.php'] as $name) {
            $archive = $this->directory . '/bad.zip';
            $zip = new ZipArchive();
            $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFromString($name, 'bad');
            $zip->close();
            try {
                (new ReleasePackage())->extract($archive, $this->directory . '/extracted', new ReleaseManifest($this->manifest('2.1.0')));
                self::fail('Запрещённый архив принят: ' . $name);
            } catch (RuntimeException $error) {
                self::assertStringContainsString('запрещённый', $error->getMessage());
            }
        }
        self::assertSame('original:.env', file_get_contents($this->directory . '/.env'));
    }

    public function testDownloadedArchiveMustMatchChecksum(): void
    {
        $catalog = new ReleaseCatalog(new MockHttpClient([new MockResponse('wrong')]), 'laiot-smarthome/laiot');
        $manifest = new ReleaseManifest($this->manifest('2.1.0') + ['size' => 5, 'sha256' => str_repeat('0', 64)]);
        $this->expectException(RuntimeException::class);
        $catalog->download(['archive_url' => 'https://github.com/laiot-smarthome/laiot/releases/download/beta-2.1.0/laiot.zip'], $manifest, $this->directory . '/download.zip');
    }

    public function testNewReleaseHasNoDeletedFileAndKeepsPersistentData(): void
    {
        $this->linux();
        $this->prepare();
        (new UpdateRunner($this->updates))->run();
        self::assertSame('succeeded', $this->state->read()['status']);
        $current = $this->directory . '/current';
        self::assertFileDoesNotExist($current . '/app/removed.php');
        self::assertFileExists($current . '/app/new.php');
        self::assertSame('original:var/files/keep.txt', file_get_contents($current . '/var/files/keep.txt'));
        self::assertSame('original:modules/third/database.sqlite', file_get_contents($current . '/modules/third/database.sqlite'));
        self::assertSame('original:.env', file_get_contents($current . '/.env'));
        self::assertSame(['beta-2.0.0'], $this->database->snapshots);
        self::assertFalse(is_file($this->state->directory . '/maintenance.flag'));
    }

    public function testFailedMigrationKeepsSnapshotAndBlocksWritesUntilUserChoice(): void
    {
        $this->linux();
        $this->prepare();
        $this->host->failMigration = true;
        try {
            (new UpdateRunner($this->updates))->run();
            self::fail('Ожидался сбой миграции.');
        } catch (RuntimeException $error) {
            self::assertSame('fixture migration interrupted', $error->getMessage());
        }
        self::assertTrue($this->state->read()['snapshot_ready']);
        self::assertTrue($this->state->read()['database_changed']);
        self::assertFileExists($this->state->directory . '/maintenance.flag');
        self::assertSame(2, $this->database->value());
        $this->state->save(['status' => 'queued', 'action' => 'rollback']);
        (new UpdateRunner($this->updates))->run();
        self::assertSame('rolled_back', $this->state->read()['status']);
        self::assertSame(1, $this->database->value());
        self::assertSame(['beta-2.0.0'], $this->database->snapshots);
        self::assertFileExists($this->directory . '/current/app/removed.php');
        self::assertSame(['laiot-core@queue.service'], $this->host->active);
    }

    public function testRetryRestoresSourceBeforeMigrationWithoutReplacingSnapshot(): void
    {
        $this->linux();
        $this->prepare();
        $this->host->failMigration = true;
        try {
            (new UpdateRunner($this->updates))->run();
        } catch (RuntimeException) {
            self::assertSame(2, $this->database->value());
        }
        $this->host->failMigration = false;
        $this->state->save(['status' => 'queued', 'action' => 'retry']);
        (new UpdateRunner($this->updates))->run();
        self::assertSame('succeeded', $this->state->read()['status']);
        self::assertSame(['beta-2.0.0'], $this->database->snapshots);
        self::assertSame(['beta-2.0.0'], $this->database->restored);
        self::assertSame(2, $this->database->value());
    }

    public function testAgentRejectsArbitraryUnitsBeforeCallingSystemctl(): void
    {
        $runner = new class () implements ProcessRunner {
            /** @var list<list<string>> */
            public array $commands = [];
            public function run(array $command, int $timeout = 20): ProcessResult
            {
                $this->commands[] = $command;
                return new ProcessResult(0, "site-user\n", '');
            }
        };
        try {
            (new UpdateAgent($runner, 'site-user'))->handle(['action' => 'update.services.stop', 'units' => ['mysql.service']]);
            self::fail('Произвольная служба принята.');
        } catch (RuntimeException) {
            self::assertCount(1, $runner->commands);
        }
    }

    public function testReleaseBuilderExcludesEnvironmentStorageAndThirdPartyModules(): void
    {
        if ((new ExecutableFinder())->find('git') === null) {
            self::markTestSkipped('Для проверки сборки нужен локальный Git.');
        }
        (new Process(['git', 'init', '--quiet', $this->directory]))->mustRun();
        (new Process(['git', '-C', $this->directory, '-c', 'user.name=Update Test', '-c', 'user.email=update@example.invalid', 'commit', '--quiet', '--allow-empty', '-m', 'fixture']))->mustRun();
        UpdateFiles::json($this->directory . '/composer.lock', ['packages' => []]);
        $tester = new CommandTester(new ReleaseBuildCommand($this->directory));
        self::assertSame(0, $tester->execute(['tag' => 'beta-2.1.0', '--output' => $this->directory . '/build']));
        $manifest = UpdateFiles::read($this->directory . '/build/laiot-release.json');
        self::assertSame(hash_file('sha256', $this->directory . '/build/laiot.zip'), $manifest['sha256']);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($this->directory . '/build/laiot.zip'));
        self::assertFalse($zip->locateName('.env'));
        self::assertFalse($zip->locateName('var/files/keep.txt'));
        self::assertFalse($zip->locateName('modules/third/module.json'));
        self::assertNotFalse($zip->locateName('release.json'));
        $zip->close();
        (new ReleasePackage())->extract($this->directory . '/build/laiot.zip', $this->directory . '/unpacked', new ReleaseManifest($manifest));
        self::assertFileExists($this->directory . '/unpacked/vendor/autoload.php');
        self::assertFileDoesNotExist($this->directory . '/unpacked/.env');
    }

    public function testModuleManagerDiscoversPersistentThirdPartyDirectory(): void
    {
        UpdateFiles::json($this->directory . '/modules/third/module.json', ['id' => 'third', 'namespace' => 'UpdateFixture\\', 'version' => '1.0.0']);
        $database = new DatabaseManager(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $database->connection()->statement('CREATE TABLE module_registry (module_id TEXT, deleted_at TEXT)');
        UpdateFiles::directory($this->directory . '/new-release');
        $manager = new ModuleManager($this->directory . '/new-release', $database, new Container(), new HelperLoader($this->directory . '/helpers'), null, $this->directory . '/modules');
        self::assertArrayHasKey('third', $manager->discovered());
        self::assertSame(realpath($this->directory . '/modules/third'), $manager->discovered()['third']['path']);
    }

    public function testEnvironmentEditorKeepsSharedFileInsteadOfReplacingReleaseLink(): void
    {
        $this->linux();
        $release = $this->directory . '/environment-release';
        UpdateFiles::write($release . '/config/app.php', '<?php return [];');
        symlink($this->directory . '/.env', $release . '/.env');
        $config = ConfigLoader::load($release);
        self::assertSame(realpath($this->directory . '/.env'), $config['environment_file']);
        $file = new EnvFile($config['environment_file']);
        $file->save(['UPDATE_CHANNEL' => 'main'], hash('sha256', $file->contents()));
        self::assertTrue(is_link($release . '/.env'));
        self::assertSame('main', Config::parse((string)file_get_contents($this->directory . '/.env'))['UPDATE_CHANNEL']);
    }

    public function testDowngradeWithoutSavedDatabaseIsRejected(): void
    {
        $this->prepare();
        $updates = new UpdateFixtureService(
            $this->updates->layout,
            $this->state,
            $this->updates->catalog,
            $this->database,
            $this->host,
            ['version' => '3.0.0', 'updates' => ['channel' => 'beta']]
        );
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('сохранённый снимок');
        $updates->preview('beta-2.1.0');
    }

    public function testIdleRunnerDoesNotOverwriteCompletedOperation(): void
    {
        $this->state->save(['status' => 'succeeded']);
        try {
            (new UpdateRunner($this->updates))->run();
            self::fail('Не должно быть повторного запуска без подтверждения.');
        } catch (RuntimeException) {
            self::assertSame('succeeded', $this->state->raw()['status']);
        }
    }

    public function testPageRendersWithoutNetworkOrProductionDatabase(): void
    {
        $app = $this->application();
        $response = $app->handle(Request::create('/admin/updates', 'GET'));
        self::assertSame(200, $response->getStatusCode(), (string)$response->getContent());
        self::assertStringContainsString('Обновление системы', (string)$response->getContent());
        self::assertStringContainsString('Сторонние модули', (string)$response->getContent());
    }

    public function testYoyoRejectsGetMutationAndMissingCsrf(): void
    {
        $app = $this->application();
        $_REQUEST = ['component' => 'system-updates/start', 'yoyo-id' => 'fixture-update'];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        Yoyo::request()->mock($_REQUEST, $_SERVER);
        $response = $app->handle(Request::create('/yoyo', 'GET'));
        self::assertSame(403, $response->getStatusCode());
        Yoyo::request()->reset();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Yoyo::request()->mock($_REQUEST, $_SERVER);
        self::assertSame(403, $app->handle(Request::create('/yoyo', 'POST'))->getStatusCode());
        self::assertSame([], $this->state->raw());
    }

    private function application(): Application
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin']);
        $app = new Application(dirname(__DIR__, 2), ['session' => $session, 'log' => 'php://stderr',
            'view_cache' => $this->directory . '/views', 'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $app->boot();
        SessionFixture::authenticate($app);
        $app->container()->instance(UpdateService::class, $this->updates);
        return $app;
    }

    private function prepare(): void
    {
        $target = $this->directory . '/releases/beta-2.1.0';
        foreach (['app/new.php', 'vendor/autoload.php', 'bin/laiot', 'public/assets/admin/laiot.css'] as $file) {
            UpdateFiles::write($target . '/' . $file, 'new');
        }
        UpdateFiles::json($target . '/release.json', $this->manifest('2.1.0'));
        $this->state->save(['id' => 'fixture-operation', 'status' => 'queued', 'source' => $this->manifest('2.0.0'),
            'target' => $this->manifest('2.1.0'), 'release' => ['id' => 'beta-2.1.0'], 'snapshot_ready' => false,
            'database_changed' => false, 'maintenance' => false, 'services' => [], 'action' => 'install', 'events' => []]);
    }

    /** @return array<string, mixed> */
    private function manifest(string $version): array
    {
        return ['id' => 'beta-' . $version, 'version' => $version, 'channel' => 'beta', 'commit' => str_repeat('a', 40),
            'php' => '>=8.2', 'extensions' => [], 'modules' => []];
    }

    private function linux(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            self::markTestSkipped('Атомарное переключение ссылок проверяется на Linux.');
        }
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            if (!is_link($path)) {
                chmod($path, 0644);
            }
            unlink($path);
        } elseif (is_dir($path)) {
            foreach (scandir($path) ?: [] as $name) {
                if ($name !== '.' && $name !== '..') {
                    $this->remove($path . '/' . $name);
                }
            }
            rmdir($path);
        }
    }
}

/** 2026-10-29 — тестовые изменения данных только в SQLite памяти. */
final class UpdateFixtureDatabase extends UpdateDatabase
{
    private PDO $sqlite;
    /** @var list<string> */
    public array $snapshots = [];
    /** @var list<string> */
    public array $restored = [];
    /** @var array<string, int> */
    private array $saved = [];
    public function __construct()
    {
        $this->sqlite = new PDO('sqlite::memory:');
        $this->sqlite->exec('CREATE TABLE fixture (value INTEGER); INSERT INTO fixture VALUES (1)');
    }
    public function snapshot(string $id, array $modules, array $services = []): void
    {
        $this->snapshots[] = $id;
        $this->saved[$id] = $this->value();
    }
    public function info(string $id): array
    {
        return [];
    }
    public function restore(string $id): void
    {
        $this->restored[] = $id;
        $this->set($this->saved[$id]);
    }
    public function value(): int
    {
        return (int)$this->sqlite->query('SELECT value FROM fixture')->fetchColumn();
    }
    public function set(int $value): void
    {
        $this->sqlite->exec('UPDATE fixture SET value=' . $value);
    }
}

/** 2026-10-29 — systemd и дочерний PHP подменены, сеть и процессы не запускаются. */
final class UpdateFixtureHost extends UpdateHost
{
    public bool $failMigration = false;
    /** @var list<string> */
    public array $active = ['laiot-core@queue.service'];
    public function __construct(private readonly UpdateFixtureDatabase $fixture)
    {
    }
    public function services(bool $includeStarting = false): array
    {
        return $this->active;
    }
    public function restartAgent(): void
    {
    }
    public function control(string $action, array $units): void
    {
        $this->active = $action === 'start' ? $units : [];
    }
    public function probe(string $root, bool $migrate = false, bool $platformOnly = false): void
    {
        if ($migrate) {
            $this->fixture->set(2);
            if ($this->failMigration) {
                throw new RuntimeException('fixture migration interrupted');
            }
        }
    }
}

/** 2026-10-29 — проверки ОС/сервисов подменены только для сценариев отказа самого конвейера. */
final readonly class UpdateFixtureService extends UpdateService
{
    public function runtimeIdentity(): string
    {
        return 'isolated fixture';
    }
    public function checks(ReleaseManifest $manifest, array $snapshot = []): array
    {
        return [['title' => 'Fixture', 'ok' => true, 'detail' => 'isolated']];
    }
    public function inventory(): array
    {
        return [];
    }
}
