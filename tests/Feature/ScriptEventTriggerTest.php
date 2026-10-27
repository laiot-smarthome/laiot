<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use MajorDoMo\Events\SystemEvents;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Queue\JobRegistry;
use MajorDoMo\Scripts\ScriptRepository;
use PHPUnit\Framework\TestCase;

/**
 * Запуск сценария по системному событию: генератор → очередь → выполнение.
 *
 * 2026-10-27 — событие ядра запускает привязанный сценарий через очередь.
 */
final class ScriptEventTriggerTest extends TestCase
{
    public function testSystemEventQueuesAndRunsBoundScript(): void
    {
        $app = $this->boot();
        $scripts = $app->container()->make(ScriptRepository::class);
        $id = $scripts->create(
            'По событию',
            '$GLOBALS["mydomo_event"] = ($params["event"] ?? "") . ":" . ($params["data"]["occurred_at"] ?? "");',
            '',
            0,
            'system.minute'
        );
        $scripts->create('Ручной', 'return 1;', '', 0);

        unset($GLOBALS['mydomo_event']);
        $app->container()->make(SystemEvents::class)->emit('system.minute', [
            'occurred_at' => '2026-10-27T12:00:00+03:00',
        ]);

        $jobs = $app->database()->connection()->table('queue_jobs')->get();
        self::assertCount(1, $jobs);
        self::assertSame('script.system.event', (string)$jobs[0]->job);
        self::assertSame('default', (string)$jobs[0]->queue);
        $payload = json_decode((string)$jobs[0]->payload, true);
        self::assertIsArray($payload);
        self::assertSame($id, $payload['id']);
        self::assertSame('system.minute', $payload['event']);

        $app->container()->make(JobRegistry::class)->handle('script.system.event', $payload);
        self::assertSame('system.minute:2026-10-27T12:00:00+03:00', $GLOBALS['mydomo_event'] ?? null);
    }

    public function testDeletedScriptIsSkippedWithoutQueueFailure(): void
    {
        $this->expectNotToPerformAssertions();
        $app = $this->boot();
        $scripts = $app->container()->make(ScriptRepository::class);
        $id = $scripts->create('Удалённый', 'throw new RuntimeException("не должен выполниться");', '', 0, 'system.day');
        $scripts->delete($id);

        $app->container()->make(JobRegistry::class)->handle('script.system.event', [
            'id' => $id,
            'event' => 'system.day',
            'data' => [],
        ]);
    }

    private function boot(): Application
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

        return $app;
    }
}
