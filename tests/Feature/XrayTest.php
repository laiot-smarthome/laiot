<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LaIOT\Database\Migrator;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use LaIOT\Services\Events\SystemEvents;
use LaIOT\Services\Maintenance\RetentionPruner;
use LaIOT\Services\Runtime\TimeoutRepository;
use LaIOT\Services\Scripts\ScriptExecutor;
use LaIOT\Services\Worker\BackgroundTaskState;
use LaIOT\Services\Worker\RuntimeSupervisor;
use LaIOT\Services\Xray\ValueSnapshot;
use LaIOT\Services\Xray\XrayJournal;
use LaIOT\Services\Xray\XrayService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * 2026-10-30 — X-ray на SQLite в памяти, без рабочего bootstrap и системного агента.
 */
final class XrayTest extends TestCase
{
    private Application $app;
    private ArraySession $session;
    private XrayJournal $journal;
    private string $errorDirectory;

    protected function setUp(): void
    {
        $this->session = new ArraySession();
        $this->errorDirectory = sys_get_temp_dir() . '/laiot-xray-errors-' . bin2hex(random_bytes(6));
        $this->session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true, 'cache' => ['driver' => 'array'], 'log' => 'php://stderr',
            'session' => $this->session, 'websocket' => ['enabled' => false],
            'logging' => ['error_directory' => $this->errorDirectory],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $this->app->boot();
        (new Migrator($this->app->database(), $this->app->root() . '/database/migrations'))->migrate();
        SessionFixture::authenticate($this->app);
        $this->journal = $this->app->container()->make(XrayJournal::class);
    }

    protected function tearDown(): void
    {
        Yoyo::request()->reset();
        $_REQUEST = [];
        unset($_SERVER['HTTP_HX_REQUEST'], $_SERVER['REQUEST_METHOD']);
        foreach (glob($this->errorDirectory . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->errorDirectory)) {
            rmdir($this->errorDirectory);
        }
    }

    public function testPropertyStreamIsIndependentOfHistoryAndWebsocketAndRollsBack(): void
    {
        $db = $this->app->database()->connection();
        $now = date('Y-m-d H:i:s');
        $db->table('classes')->insert(['id' => 101, 'title' => 'XraySensor', 'created_at' => $now, 'updated_at' => $now]);
        $db->table('objects')->insert(['id' => 101, 'class_id' => 101, 'title' => 'Probe', 'created_at' => $now, 'updated_at' => $now]);
        $db->table('properties')->insert(['id' => 101, 'class_id' => 101, 'title' => 'value', 'keep_history' => 0, 'created_at' => $now, 'updated_at' => $now]);

        self::assertTrue(sg('Probe.value', 'first'));
        self::assertTrue(sg('Probe.value', 'second'));
        self::assertTrue(sg('Probe.value', 'second'));
        self::assertSame(2, $db->table('xray_entries')->where('kind', 'property')->count());
        self::assertSame(0, $db->table('property_history')->count());
        self::assertSame(0, $db->table('websocket_events')->count());
        $rows = $this->journal->listing('property', 'Probe.value', $this->journal->maxId(), 1);
        self::assertSame(['object_id' => 101, 'property_id' => 101, 'old_value' => 'first', 'new_value' => 'second'], $rows[0]['payload']);
        try {
            $this->app->database()->transaction(static function (): void {
                sg('Probe.value', 'rollback');
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $error) {
            self::assertSame('rollback', $error->getMessage());
        }
        self::assertSame('second', gg('Probe.value'));
        self::assertSame(2, $db->table('xray_entries')->count());
    }

    public function testBothDumpFunctionsKeepTypesAndNeverPrintOrStop(): void
    {
        $value = ['text' => '<script>alert(1)</script>', 'zero' => 0, 'false' => false, 'null' => null];
        ob_start();
        self::assertSame($value, debmes($value));
        self::assertSame($value, dump($value));
        self::assertSame('', ob_get_clean());
        $rows = $this->journal->listing('debug', '', $this->journal->maxId(), 1);
        self::assertCount(2, $rows);
        self::assertSame($rows[0]['payload'], $rows[1]['payload']);
        self::assertStringContainsString('XrayTest.php:', $rows[0]['source']);
        self::assertSame('array', $rows[0]['payload']['value']['type']);
        self::assertSame('bool', $rows[0]['payload']['value']['children'][2]['node']['type']);
        $page = $this->update('open', [(int)$rows[0]['id']]);
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('&lt;script&gt;', (string)$page->getContent());
        self::assertStringNotContainsString('<script>alert(1)</script>', (string)$page->getContent());
    }

    public function testSnapshotsBoundRecursionAndDoNotInvokeMagicMethods(): void
    {
        $object = new class () {
            public mixed $self;
            public function __debugInfo(): array
            {
                throw new RuntimeException('Must not run');
            }
        };
        $object->self = $object;
        $array = [];
        $array['self'] = &$array;
        $snapshot = new ValueSnapshot();
        self::assertStringContainsString('повторная ссылка', (string)json_encode($snapshot->capture($object), JSON_UNESCAPED_UNICODE));
        self::assertLessThan(5000, strlen((string)json_encode($snapshot->capture($array))));
        self::assertSame('binary', $snapshot->capture("\xff\x00")['type']);
        self::assertSame('INF', $snapshot->capture(INF)['value']);
        self::assertTrue($snapshot->capture(range(0, 1000))['truncated']);
    }

    public function testScriptSuccessFailureAndDumpContextAreRecorded(): void
    {
        $executor = $this->app->container()->make(ScriptExecutor::class);
        $result = $executor->execute('debmes(["debug" => true]); echo "output"; return 42;', [], true, 'Demo');
        self::assertTrue($result['ok']);
        self::assertSame('output', $result['output']);
        $failed = $executor->execute('throw new RuntimeException("broken");', [], true, 'Broken');
        self::assertFalse($failed['ok']);
        $rows = $this->journal->listing('script', '', $this->journal->maxId(), 1);
        self::assertCount(2, $rows);
        self::assertFalse($rows[0]['payload']['ok']);
        self::assertSame(42, $rows[1]['payload']['result']['value']);
        self::assertSame('output', $rows[1]['payload']['output']);
        $debug = $this->journal->listing('debug', 'Demo', $this->journal->maxId(), 1);
        self::assertCount(1, $debug);
        $executor->runValue('return false;', [], true, 'Background');
        self::assertCount(1, $this->journal->listing('script', 'Background', $this->journal->maxId(), 1));
    }

    public function testSystemEventsAndSubscriptionsAreVisible(): void
    {
        $events = $this->app->container()->make(SystemEvents::class);
        $events->subscribe('example', 'system.minute', static function (): void {
        });
        $events->emit('system.minute', ['count' => 1]);
        self::assertCount(1, $this->journal->listing('event', 'system.minute', $this->journal->maxId(), 1));
        self::assertContains(['module' => 'example', 'event' => 'system.minute', 'handlers' => 1], $events->subscriptions());
        $page = $this->update('changeTab', ['event']);
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('Модуль example', (string)$page->getContent());
    }

    public function testRetentionAndArchiveSnapshot(): void
    {
        for ($i = 0; $i < 2005; $i++) {
            debmes($i);
        }
        $snapshot = $this->journal->maxId();
        debmes('new');
        $rows = $this->journal->listing('debug', '', $snapshot, 41);
        self::assertCount(5, $rows);
        self::assertSame(4, $rows[0]['payload']['value']['value']);
        $db = $this->app->database()->connection();
        $db->table('xray_entries')->where('id', $snapshot)->update(['created_at' => date('Y-m-d H:i:s', time() - 4 * 86400)]);
        self::assertNull($this->journal->find($snapshot));
        $pruner = $this->app->container()->make(RetentionPruner::class);
        self::assertSame(1, $pruner->prune(true)['xray_entries']);
        self::assertSame(2006, $db->table('xray_entries')->count());
        self::assertSame(1, $pruner->prune()['xray_entries']);
        self::assertSame(2005, $db->table('xray_entries')->count());
    }

    public function testCancellationIsAddressedAndProtectsClaimedTimer(): void
    {
        $timers = $this->app->container()->make(TimeoutRepository::class);
        $first = $timers->set('same', 'return 1;', 100);
        $second = $timers->set('same', 'return 2;', 100);
        self::assertSame(403, $this->update('cancelTimer', [$first], 'POST', [], false)->getStatusCode());
        self::assertGreaterThanOrEqual(400, $this->update('cancelTimer', [$first])->getStatusCode());
        self::assertSame(200, $this->update('cancelTimer', [$first], 'POST')->getStatusCode());
        self::assertNotNull($this->app->database()->connection()->table('runtime_timeouts')->where('id', $first)->value('deleted_at'));
        self::assertNull($this->app->database()->connection()->table('runtime_timeouts')->where('id', $second)->value('deleted_at'));
        $this->app->database()->connection()->table('runtime_timeouts')->where('id', $second)->update(['claimed_at' => date('Y-m-d H:i:s')]);
        self::assertFalse($timers->cancel($second));
    }

    public function testAllViewsRenderWithoutCallingSystemAgent(): void
    {
        debmes('probe');
        $initial = $this->app->handle(Request::create('/xray'));
        self::assertSame(200, $initial->getStatusCode());
        self::assertStringContainsString('Отладка кода', (string)$initial->getContent());
        self::assertStringContainsString('yoyo:spin-on=', (string)$initial->getContent());
        foreach (['property', 'debug', 'script', 'event', 'timers', 'cycles'] as $tab) {
            $response = $this->update('changeTab', [$tab], 'POST');
            self::assertSame(200, $response->getStatusCode(), (string)$response->getContent());
        }
    }

    public function testPausedViewKeepsItsSnapshotUntilResumed(): void
    {
        debmes('before-pause');
        $snapshot = $this->journal->maxId();
        debmes('after-pause');
        $state = ['paused' => true, 'snapshot' => $snapshot, 'tab' => 'debug'];
        $paused = $this->update('refresh', [], 'POST', $state);
        self::assertSame(200, $paused->getStatusCode());
        self::assertStringContainsString('before-pause', (string)$paused->getContent());
        self::assertStringNotContainsString('after-pause', (string)$paused->getContent());
        $resumed = $this->update('togglePause', [], 'POST', $state);
        self::assertSame(200, $resumed->getStatusCode());
        self::assertStringContainsString('after-pause', (string)$resumed->getContent());
    }

    public function testTabQueryRestoresTheRequestedSection(): void
    {
        $response = $this->app->handle(Request::create('/xray?tab=event'));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Системные события', (string)$response->getContent());
        self::assertStringContainsString('href="/xray?tab=debug"', (string)$response->getContent());
    }

    public function testUnknownProcessesAreRejectedBeforeAgentConnection(): void
    {
        $this->expectException(ValidationException::class);
        $this->app->container()->make(XrayService::class)->control('core', '../../nginx', 'restart');
    }

    public function testSupervisorCatalogHasOneWorkerPerModuleAndNoCompetingEventLanes(): void
    {
        $supervisor = $this->app->container()->make(RuntimeSupervisor::class);
        $targets = array_map(static fn (array $process): string => $process['kind'] . ':' . $process['id'], $supervisor->desired());
        self::assertContains('core:events', $targets);
        self::assertContains('worker:mqtt', $targets);
        self::assertContains('worker:zigbee', $targets);
        self::assertContains('core:module.zigbee', $targets);
        self::assertNotContains('core:events.0', $targets);
        self::assertNotContains('core:websocket', $targets);
        self::assertSame(count($targets), count(array_unique($targets)));
        $count = count(spl_autoload_functions());
        $this->app->modules()->refreshCatalog();
        $this->app->modules()->refreshCatalog();
        self::assertCount($count, spl_autoload_functions());
    }

    public function testSupervisorRecordsFailuresAndRestartsWithoutDuplicatingErrors(): void
    {
        $supervisor = $this->app->container()->make(RuntimeSupervisor::class);
        $status = ['active' => 'active', 'sub' => 'running', 'pid' => 123, 'started' => date('c'), 'restarts' => 1];
        $supervisor->observe('core', 'queue', $status);
        $supervisor->observe('core', 'queue', $status);
        self::assertSame(1, $this->app->database()->connection()->table('system_errors')->count());
        $status['active'] = 'failed';
        $supervisor->observe('core', 'queue', $status);
        $supervisor->observe('core', 'queue', $status);
        self::assertSame(2, $this->app->database()->connection()->table('system_errors')->count());
        $this->app->cache()->put('runtime.manual_stop.core.queue', true);
        $status['active'] = 'inactive';
        $supervisor->observe('core', 'queue', $status);
        self::assertFalse($this->app->database()->connection()->table('system_errors')->where('context', 'like', '%inactive%')->exists());
    }

    public function testHeartbeatAndButtonsReflectTheRunningProcess(): void
    {
        $state = $this->app->container()->make(BackgroundTaskState::class);
        self::assertTrue($state->acquireTask('core', 'queue', 123));
        $state->refreshTask('core', 'queue', 123);
        self::assertGreaterThan(0, $this->app->cache()->get('worker.heartbeat.core.queue'));
        $response = $this->update('changeTab', ['cycles'], 'POST', ['search' => 'queue']);
        $html = (string)$response->getContent();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('aria-label="Перезапустить"', $html);
        self::assertStringContainsString('aria-label="Остановить"', $html);
        $state->releaseTask('core', 'queue', 123);
        self::assertNull($this->app->cache()->get('worker.heartbeat.core.queue'));
    }

    public function testReadAndWriteActionsRequirePermissionEvenViaYoyo(): void
    {
        $user = $this->session->user();
        self::assertNotNull($user);
        $user['is_admin'] = false;
        $this->session->login($user);
        self::assertSame(403, $this->app->handle(Request::create('/xray'))->getStatusCode());
        self::assertGreaterThanOrEqual(400, $this->update('render')->getStatusCode());
        self::assertGreaterThanOrEqual(400, $this->update('control', ['core', 'queue', 'restart'], 'POST')->getStatusCode());
    }

    /**
     * @param list<mixed> $args
     * @param array<string, mixed> $state
     */
    private function update(string $action, array $args = [], string $method = 'GET', array $state = [], bool $csrf = true): \Symfony\Component\HttpFoundation\Response
    {
        $_REQUEST = $state + ['component' => 'xray/' . $action, 'yoyo-id' => 'test-xray', 'actionArgs' => $args];
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        Yoyo::request()->mock($_REQUEST, $_SERVER);
        try {
            return $this->app->handle(Request::create('/yoyo', $method, $_REQUEST, [], [], $csrf ? ['HTTP_X_CSRF_TOKEN' => $this->session->csrfToken()] : []));
        } finally {
            Yoyo::request()->reset();
            $_REQUEST = [];
            unset($_SERVER['HTTP_HX_REQUEST'], $_SERVER['REQUEST_METHOD']);
        }
    }
}
