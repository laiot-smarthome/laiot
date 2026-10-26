<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class CoreSettingsTest extends TestCase
{
    public function testSettingsCanBeListedAndSaved(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        $app->database()->schema()->create('settings', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->text('value')->nullable();
            $table->string('title')->default('');
            $table->string('type')->default('text');
            $table->timestamps();
            $table->softDeletes();
        });
        $app->database()->connection()->table('settings')->insert([
            'name' => 'SITE_TITLE',
            'value' => 'Old',
            'title' => 'Название сайта',
            'type' => 'text',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $page = $app->handle(Request::create('/module/settings', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('SITE_TITLE', (string)$page->getContent());

        $save = $app->handle(Request::create('/module/settings/1', 'POST', [
            '_token' => $session->csrfToken(),
            'value' => 'New title',
        ]));
        self::assertSame(302, $save->getStatusCode());
        self::assertSame('New title', $app->database()->connection()->table('settings')->value('value'));
    }
}
