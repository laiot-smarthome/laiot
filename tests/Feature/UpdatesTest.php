<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use InvalidArgumentException;
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
 * 2026-10-30 — диалог установки, сохранение ошибок, обязательное подтверждение и отказ запуска при блокировках.
 * 2026-10-30 — реальный POST Yoyo обновляет каталог повторно; пагинация, сортировка и безопасные карточки.
 * 2026-10-30 — переход с неизвестной версии на первый релиз и отказ сборки несовместимых core-модулей.
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

    public function testUndefinedVersionCanUpgradeToFirstRelease(): void
    {
        $defaults = require dirname(__DIR__, 2) . '/config/app.php';
        self::assertSame('0.0.0', $defaults['version']);
        UpdateFiles::json($this->directory . '/releases/beta-0.0.1/release.json', $this->manifest('0.0.1'));
        foreach ([[], ['version' => null], ['version' => '']] as $config) {
            $updates = new UpdateFixtureService(
                $this->updates->layout,
                $this->state,
                $this->updates->catalog,
                $this->database,
                $this->host,
                $config + ['updates' => ['channel' => 'beta']]
            );
            self::assertSame('0.0.0', $updates->current()['version']);
            self::assertSame('beta-0.0.0', $updates->current()['id']);
            self::assertTrue($updates->preview('beta-0.0.1')['ready']);
        }
    }

    public function testInstalledReleaseVersionTakesPriorityOverFallback(): void
    {
        UpdateFiles::write($this->directory . '/config/app.php', '<?php return ["version" => "0.0.0"];');
        UpdateFiles::json($this->directory . '/release.json', $this->manifest('0.0.1'));
        self::assertSame('0.0.1', ConfigLoader::load($this->directory)['version']);
        self::assertSame('0.0.1', $this->updates->current()['version']);
        self::assertSame('beta-0.0.1', $this->updates->current()['id']);
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

    public function testCatalogLoadsEveryPageAndRevalidatesHttpCache(): void
    {
        $first = [];
        for ($index = 0; $index < 100; $index++) {
            $first[] = $this->githubRelease('2.1.' . $index);
        }
        $responses = [new MockResponse(json_encode($first, JSON_THROW_ON_ERROR)), new MockResponse(json_encode([$this->githubRelease('2.2.0')], JSON_THROW_ON_ERROR))];
        $http = new MockHttpClient($responses);
        $catalog = new ReleaseCatalog($http, 'laiot-smarthome/laiot');
        $releases = $catalog->releases();
        self::assertCount(101, $releases);
        self::assertSame('beta-2.2.0', $releases[100]['id']);
        foreach ($responses as $index => $response) {
            self::assertStringEndsWith('per_page=100&page=' . ($index + 1), $response->getRequestUrl());
            self::assertStringContainsString('cache-control: no-cache, max-age=0', strtolower(implode("\n", $response->getRequestOptions()['headers'])));
        }
    }

    public function testListingMergesLocalVersionsAndSortsSemverWithinChannel(): void
    {
        UpdateFiles::json($this->state->directory . '/catalog.json', ['repository' => 'laiot-smarthome/laiot', 'releases' => [
            ['id' => 'beta-2.9.0', 'channel' => 'beta'], ['id' => 'beta-2.10.0', 'channel' => 'beta'],
            ['id' => 'beta-2.0.0', 'channel' => 'beta'], ['id' => 'main-9.0.0', 'channel' => 'main'],
        ]]);
        UpdateFiles::json($this->directory . '/releases/beta-2.9.0/release.json', $this->manifest('2.9.0'));
        UpdateFiles::json($this->directory . '/releases/beta-2.5.0/release.json', $this->manifest('2.5.0'));
        $releases = $this->updates->listing('beta');
        self::assertSame(['beta-2.10.0', 'beta-2.9.0', 'beta-2.5.0'], array_column($releases, 'id'));
        self::assertTrue($releases[1]['local']);
    }

    public function testCheckUpdatesActionFetchesNewReleasesOnEveryClick(): void
    {
        $http = new MockHttpClient([
            new MockResponse(json_encode([$this->githubRelease('2.1.0'), $this->githubRelease('2.2.0')], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([$this->githubRelease('2.1.0'), $this->githubRelease('2.2.0'), $this->githubRelease('2.10.0')], JSON_THROW_ON_ERROR)),
        ]);
        $this->useCatalog($http);
        UpdateFiles::json($this->state->directory . '/catalog.json', ['repository' => 'laiot-smarthome/laiot', 'releases' => [
            ['id' => 'beta-2.1.0', 'channel' => 'beta', 'title' => 'Старый список', 'notes' => ''],
        ]]);
        $app = $this->application();
        foreach ([['beta-2.2.0', 'beta-2.1.0'], ['beta-2.10.0', 'beta-2.2.0', 'beta-2.1.0']] as $expected) {
            Yoyo::request()->reset();
            $_REQUEST = ['component' => 'system-updates/checkUpdates', 'yoyo-id' => 'fixture-update'];
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['HTTP_HX_REQUEST'] = 'true';
            Yoyo::request()->mock($_REQUEST, $_SERVER);
            $response = $app->handle(Request::create('/yoyo', 'POST', [], [], [], ['HTTP_X_CSRF_TOKEN' => $app->session()->csrfToken()]));
            self::assertSame(200, $response->getStatusCode(), (string)$response->getContent());
            self::assertSame($expected, array_column($this->updates->listing('beta'), 'id'));
            foreach ($expected as $id) {
                self::assertStringContainsString('data-release="' . $id . '"', (string)$response->getContent());
            }
        }
        self::assertSame(2, $http->getRequestsCount());
    }

    public function testFailedCheckKeepsPreviousCatalogAndReportsError(): void
    {
        $this->useCatalog(new MockHttpClient([new MockResponse('{}', ['http_code' => 503])]));
        $cached = ['repository' => 'laiot-smarthome/laiot', 'checked_at' => 'previous', 'releases' => []];
        UpdateFiles::json($this->state->directory . '/catalog.json', $cached);
        $app = $this->application();
        $_REQUEST = ['component' => 'system-updates/checkUpdates', 'yoyo-id' => 'fixture-update'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        Yoyo::request()->mock($_REQUEST, $_SERVER);
        $response = $app->handle(Request::create('/yoyo', 'POST', [], [], [], ['HTTP_X_CSRF_TOKEN' => $app->session()->csrfToken()]));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame($cached, UpdateFiles::read($this->state->directory . '/catalog.json'));
        self::assertStringContainsString('danger', (string)$response->headers->get('Yoyo-Browser-Event'));
    }

    public function testReleaseCardsHaveSafeNotesAndIndependentReadAndInstallActions(): void
    {
        $release = $this->githubRelease('2.1.0');
        $release['body'] = "# Release\n\n- First change\n- Second change\n- Third change\n- Fourth change\n\n<script>alert('bad')</script>\n\n[Unsafe](javascript:alert(1))";
        $this->useCatalog(new MockHttpClient([new MockResponse(json_encode([$release], JSON_THROW_ON_ERROR))]));
        $this->updates->refresh();
        $response = $this->application()->handle(Request::create('/admin/updates', 'GET'));
        $html = (string)$response->getContent();
        self::assertSame(200, $response->getStatusCode(), $html);
        self::assertStringContainsString('Новая версия', $html);
        self::assertStringContainsString('First change', $html);
        self::assertStringContainsString('Читать полностью', $html);
        self::assertStringContainsString('Подготовить установку', $html);
        self::assertStringContainsString('https://github.com/laiot-smarthome/laiot/releases/tag/beta-2.1.0', $html);
        self::assertStringContainsString('id="update-release-notes"', $html);
        self::assertStringNotContainsString('<script>alert', $html);
        self::assertStringNotContainsString('href="javascript:', $html);
        $before = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($before);
        $xpath = new \DOMXPath($dom);
        $card = $xpath->query('//article[@data-release="beta-2.1.0"]')->item(0);
        self::assertNotNull($card);
        self::assertSame(0, $xpath->query('.//button//button', $card)->length);
        $more = $xpath->query('.//button[contains(@class, "__more")]', $card)->item(0);
        self::assertInstanceOf(\DOMElement::class, $more);
        self::assertStringContainsString('Fourth change', $more->getAttribute('x-on:click'));
        self::assertSame(3, $xpath->query('.//div[contains(@class, "__summary")]//li', $card)->length);
    }

    public function testCarouselShowsTenLatestReleasesAndCurrentVersionOnce(): void
    {
        $releases = [$this->githubRelease('2.0.0')];
        for ($version = 1; $version <= 14; $version++) {
            $releases[] = $this->githubRelease('2.' . $version . '.0');
        }
        $this->useCatalog(new MockHttpClient([new MockResponse(json_encode($releases, JSON_THROW_ON_ERROR))]));
        $this->updates->refresh();
        $response = $this->application()->handle(Request::create('/admin/updates', 'GET'));
        $html = (string)$response->getContent();
        self::assertSame(200, $response->getStatusCode(), $html);
        preg_match_all('/<article[^>]+data-release="([^"]+)"/', $html, $cards);
        self::assertSame(['beta-2.14.0', 'beta-2.13.0', 'beta-2.12.0', 'beta-2.11.0', 'beta-2.10.0', 'beta-2.9.0', 'beta-2.8.0', 'beta-2.7.0', 'beta-2.6.0', 'beta-2.5.0', 'beta-2.0.0'], $cards[1]);
        self::assertSame(1, substr_count($html, 'data-current="true"'));
        self::assertStringContainsString('Моя версия', $html);
        self::assertStringContainsString('Текущая версия', $html);
        self::assertSame(['beta-2.0.0'], array_column($this->updates->listing('main', true), 'id'));
        self::assertSame([], $this->updates->listing('main'));
        self::assertCount(14, $this->updates->listing('beta'));
        $installed = substr($html, (int)strpos($html, 'data-release="beta-2.0.0"'));
        $installed = strstr($installed, '</article>', true);
        self::assertStringNotContainsString('data-update-action="inspect"', $installed);
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
        $tester = $this->releaseBuilder();
        UpdateFiles::json($this->directory . '/modules/core/supported/module.json', [
            'id' => 'supported', 'type' => 'core', 'namespace' => 'Supported\\', 'version' => '1.0.0',
            'requires' => ['laiot' => '>=0.0.0'],
        ]);
        self::assertSame(0, $tester->execute(['tag' => 'beta-0.0.1', '--output' => $this->directory . '/build']));
        $manifest = UpdateFiles::read($this->directory . '/build/laiot-release.json');
        self::assertSame('0.0.1', $manifest['version']);
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

    public function testReleaseBuilderRejectsIncompatibleCoreModuleBeforePackaging(): void
    {
        $tester = $this->releaseBuilder();
        UpdateFiles::json($this->directory . '/modules/core/future/module.json', [
            'id' => 'future', 'type' => 'core', 'namespace' => 'Future\\', 'version' => '1.0.0',
            'requires' => ['laiot' => '>=2.0.0'],
        ]);
        try {
            $tester->execute(['tag' => 'beta-0.0.1', '--output' => $this->directory . '/build']);
            self::fail('Несовместимый core-модуль попал в релиз.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('future требует laiot >=2.0.0', $error->getMessage());
        }
        self::assertFileDoesNotExist($this->directory . '/build/laiot.zip');
        self::assertFileDoesNotExist($this->directory . '/build/laiot-release.json');
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
        foreach (['start', 'checkUpdates'] as $action) {
            Yoyo::request()->reset();
            $_REQUEST = ['component' => 'system-updates/' . $action, 'yoyo-id' => 'fixture-update'];
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['HTTP_HX_REQUEST'] = 'true';
            Yoyo::request()->mock($_REQUEST, $_SERVER);
            $response = $app->handle(Request::create('/yoyo', 'GET'));
            self::assertSame(403, $response->getStatusCode());
        }
        Yoyo::request()->reset();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Yoyo::request()->mock($_REQUEST, $_SERVER);
        self::assertSame(403, $app->handle(Request::create('/yoyo', 'POST'))->getStatusCode());
        self::assertSame([], $this->state->raw());
    }

    /** @return array<string, mixed> */
    private function githubRelease(string $version): array
    {
        $id = 'beta-' . $version;
        $prefix = 'https://github.com/laiot-smarthome/laiot/releases/download/' . $id . '/';
        return ['draft' => false, 'tag_name' => $id, 'name' => $id, 'body' => '- Изменение системы', 'published_at' => '2026-10-30T08:00:00Z', 'assets' => [
            ['name' => 'laiot.zip', 'browser_download_url' => $prefix . 'laiot.zip', 'size' => 1048576],
            ['name' => 'laiot-release.json', 'browser_download_url' => $prefix . 'laiot-release.json'],
        ]];
    }

    private function useCatalog(MockHttpClient $http): void
    {
        $this->updates = new UpdateFixtureService(
            $this->updates->layout,
            $this->state,
            new ReleaseCatalog($http, 'laiot-smarthome/laiot'),
            $this->database,
            $this->host,
            $this->updates->config
        );
    }

    public function testInspectOpensInstallDialogWithConsentAndLinkedSubmit(): void
    {
        UpdateFiles::json($this->directory . '/releases/beta-2.1.0/release.json', $this->manifest('2.1.0'));
        $response = $this->updateRequest($this->application(), 'inspect', ['actionArgs' => ['beta-2.1.0']], 'GET');
        $html = (string)$response->getContent();
        self::assertSame(200, $response->getStatusCode(), $html);
        $xpath = $this->htmlXPath($html);
        self::assertSame(1, $xpath->query('//*[@id="update-release-install" and @role="dialog"]')->length);
        self::assertSame(1, $xpath->query('//*[@id="update-release-install"]//form[@id="update-install-form"]')->length);
        self::assertSame(1, $xpath->query('//*[@id="update-release-install"]//input[@name="confirmed" and @x-model="confirmed" and @required]')->length);
        $submit = $xpath->query('//*[@id="update-release-install"]//button[@type="submit"]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $submit);
        self::assertSame('update-install-form', $submit->getAttribute('form'));
        self::assertFalse($submit->hasAttribute('disabled'));
        self::assertStringContainsString('!confirmed', $submit->getAttribute('x-bind:disabled'));
        self::assertStringContainsString("mdModal.show('update-release-install')", $html);
        self::assertSame(0, $xpath->query('//section[contains(@class, "md-upd-preview")]')->length);
        self::assertSame(1, $xpath->query('//*[@id="update-release-install"]//img[contains(@src, "/success.png")]')->length);
        self::assertSame(1, $xpath->query('//*[@id="update-release-install"]//ul[contains(@class, "md-upd-install-checks")]//details[contains(@class, "md-spoiler") and not(@open)]')->length);
        self::assertSame(1, $xpath->query('//*[@id="update-release-install"]//details[contains(@class, "md-upd-install-notes") and contains(@class, "md-spoiler")]')->length);
        $close = $xpath->query('//*[@id="update-release-install"]//button[contains(@class, "btn-close")]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $close);
        self::assertSame('$store.mdModal.close()', $close->getAttribute('x-on:click'));
        self::assertFalse($close->hasAttribute('hx-get'));
        $dialog = $xpath->query('//*[@id="update-release-install"]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $dialog);
        self::assertSame('$store.mdModal.close()', $dialog->getAttribute('x-on:keydown.escape.window'));
        self::assertSame('trap($event)', $dialog->getAttribute('x-on:keydown.tab'));
    }

    public function testFailedChecksStayVisibleAndCannotBeBypassedWithConsent(): void
    {
        UpdateFiles::json($this->directory . '/releases/beta-2.1.0/release.json', $this->manifest('2.1.0'));
        $this->updates = new UpdateFixtureService(
            $this->updates->layout,
            $this->state,
            $this->updates->catalog,
            $this->database,
            $this->host,
            $this->updates->config + ['fixture_checks' => [
                ['title' => 'База данных', 'ok' => true, 'detail' => 'SQLite fixture'],
                ['title' => 'PHP и расширения', 'ok' => false, 'detail' => 'Необходимо расширение PHP zip'],
            ]]
        );
        $app = $this->application();
        $response = $this->updateRequest($app, 'inspect', ['actionArgs' => ['beta-2.1.0']], 'GET');
        $xpath = $this->htmlXPath((string)$response->getContent());
        self::assertSame(1, $xpath->query('//*[@id="update-release-install"]//button[@type="submit" and @disabled]')->length);
        self::assertSame(1, $xpath->query('//*[@id="update-release-install"]//input[@name="confirmed" and @disabled]')->length);
        self::assertStringContainsString('Необходимо расширение PHP zip', (string)$response->getContent());
        self::assertSame(1, $xpath->query('//*[@id="update-release-install"]//img[contains(@src, "/error.png")]')->length);
        self::assertSame(1, $xpath->query('//*[@id="update-release-install"]//li[@data-ok="false"]//i[contains(@class, "ti-circle-x")]')->length);
        $first = $xpath->query('//*[@id="update-release-install"]//li')->item(0);
        self::assertInstanceOf(\DOMElement::class, $first);
        self::assertSame('false', $first->getAttribute('data-ok'));
        $response = $this->updateRequest($app, 'start', ['release' => 'beta-2.1.0', 'confirmed' => '1']);
        self::assertStringContainsString('id="update-release-install"', (string)$response->getContent());
        self::assertStringContainsString('Исправьте ошибки предварительной проверки.', (string)$response->getContent());
        self::assertSame([], $this->state->raw());
        self::assertSame(0, $this->host->launches);
    }

    public function testMissingConsentKeepsInstallDialogAndDoesNotStartUpdate(): void
    {
        UpdateFiles::json($this->directory . '/releases/beta-2.1.0/release.json', $this->manifest('2.1.0'));
        $response = $this->updateRequest($this->application(), 'start', ['release' => 'beta-2.1.0']);
        $xpath = $this->htmlXPath((string)$response->getContent());
        self::assertSame(1, $xpath->query('//*[@id="update-release-install"]//input[@name="confirmed" and not(@checked)]')->length);
        self::assertStringContainsString('Подтвердите остановку системы', (string)$response->getContent());
        self::assertSame([], $this->state->raw());
        self::assertSame(0, $this->host->launches);
    }

    public function testConfirmedReadyReleaseIsQueuedAndRedirectsToRecovery(): void
    {
        UpdateFiles::json($this->directory . '/releases/beta-2.1.0/release.json', $this->manifest('2.1.0'));
        $response = $this->updateRequest($this->application(), 'start', ['release' => 'beta-2.1.0', 'confirmed' => '1']);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('/admin/updates/recovery', $response->headers->get('HX-Redirect'));
        self::assertSame('queued', $this->state->raw()['status']);
        self::assertSame('beta-2.1.0', $this->state->raw()['target']['id']);
        self::assertSame(1, $this->host->launches);
    }

    public function testExistingOperationExplainsWhyInstallIsBlockedInDialog(): void
    {
        UpdateFiles::json($this->directory . '/releases/beta-2.1.0/release.json', $this->manifest('2.1.0'));
        $this->state->save(['status' => 'failed']);
        $response = $this->updateRequest($this->application(), 'inspect', ['actionArgs' => ['beta-2.1.0']], 'GET');
        $xpath = $this->htmlXPath((string)$response->getContent());
        self::assertSame(1, $xpath->query('//*[@id="update-release-install"]//button[@type="submit" and @disabled]')->length);
        self::assertSame(1, $xpath->query('//*[@id="update-release-install"]//a[@href="/admin/updates/recovery"]')->length);
        self::assertStringContainsString('Сначала завершите или восстановите предыдущую операцию.', (string)$response->getContent());
        self::assertSame(0, $this->host->launches);
    }

    /** @param array<string, mixed> $parameters */
    private function updateRequest(Application $app, string $action, array $parameters, string $method = 'POST'): \Symfony\Component\HttpFoundation\Response
    {
        Yoyo::request()->reset();
        $_REQUEST = ['component' => 'system-updates/' . $action, 'yoyo-id' => 'fixture-update'] + $parameters;
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        Yoyo::request()->mock($_REQUEST, $_SERVER);
        return $app->handle(Request::create('/yoyo', $method, $parameters, [], [], ['HTTP_X_CSRF_TOKEN' => $app->session()->csrfToken()]));
    }

    private function htmlXPath(string $html): \DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new \DOMXPath($dom);
    }

    private function releaseBuilder(): CommandTester
    {
        if ((new ExecutableFinder())->find('git') === null) {
            self::markTestSkipped('Для проверки сборки нужен локальный Git.');
        }
        (new Process(['git', 'init', '--quiet', $this->directory]))->mustRun();
        (new Process(['git', '-C', $this->directory, '-c', 'core.hooksPath=' . $this->directory . '/no-hooks', '-c', 'user.name=Update Test', '-c', 'user.email=update@example.invalid', 'commit', '--quiet', '--allow-empty', '-m', 'fixture']))->mustRun();
        UpdateFiles::json($this->directory . '/composer.lock', ['packages' => []]);
        return new CommandTester(new ReleaseBuildCommand($this->directory));
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

/**
 * 2026-10-29 — systemd и дочерний PHP подменены, сеть и процессы не запускаются.
 * 2026-10-30 — запуск службы учитывается без обращения к системному агенту.
 */
final class UpdateFixtureHost extends UpdateHost
{
    public int $launches = 0;
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
    public function launch(): void
    {
        $this->launches++;
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

/**
 * 2026-10-29 — проверки ОС/сервисов подменены только для сценариев отказа самого конвейера.
 * 2026-10-30 — отказы предварительных проверок задаются тестовой конфигурацией.
 */
final readonly class UpdateFixtureService extends UpdateService
{
    public function runtimeIdentity(): string
    {
        return 'isolated fixture';
    }
    public function checks(ReleaseManifest $manifest, array $snapshot = []): array
    {
        return $this->config['fixture_checks'] ?? [['title' => 'Fixture', 'ok' => true, 'detail' => 'isolated']];
    }
    public function inventory(): array
    {
        return [];
    }
}
