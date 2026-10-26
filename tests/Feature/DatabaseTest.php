<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Database\Setting;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'view_cache' => sys_get_temp_dir() . '/mydomo-views-' . bin2hex(random_bytes(4)),
            'log' => 'php://stderr',
            'session' => new ArraySession(),
            'database' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
        $this->app->boot();

        $this->app->database()->schema()->create('settings', static function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('NAME', 50);
            $table->text('VALUE')->nullable();
            $table->string('TITLE')->default('');
            $table->string('TYPE')->default('');
        });

        Setting::query()->create([
            'NAME' => 'SITE_TITLE',
            'VALUE' => 'MyDoMo',
            'TITLE' => 'Название сайта',
            'TYPE' => 'text',
        ]);
    }

    protected function tearDown(): void
    {
        $this->app->database()->connection()->disconnect();
        parent::tearDown();
    }

    public function testRepositoryReadsSettingThroughEloquent(): void
    {
        $repository = $this->app->container()->make(\MajorDoMo\Database\SettingsRepository::class);

        self::assertSame('MyDoMo', $repository->get('SITE_TITLE'));
        self::assertNull($repository->get('UNKNOWN'));
        self::assertSame('fallback', $repository->get('UNKNOWN', 'fallback'));
    }

    public function testHealthReportsDatabaseOk(): void
    {
        $response = $this->app->handle(Request::create('/health', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('"status":"ok"', (string)$response->getContent());
        self::assertStringContainsString('"database":"ok"', (string)$response->getContent());
    }

}
