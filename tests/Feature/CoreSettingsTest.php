<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * Настройки: список, разделы по префиксу и сохранение значения.
 *
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 * 2026-10-26 — добавлена группировка по префиксу имени.
 */
final class CoreSettingsTest extends TestCase
{
    public function testSettingsCanBeListedAndSaved(): void
    {
        [$app, $session] = $this->boot([
            ['name' => 'SITE_TITLE', 'value' => 'Old', 'title' => 'Название сайта', 'type' => 'text'],
        ]);

        $page = $app->handle(Request::create('/settings', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('SITE_TITLE', (string)$page->getContent());

        $save = $app->handle(Request::create('/settings/1', 'POST', [
            '_token' => $session->csrfToken(),
            'value' => 'New title',
        ]));
        self::assertSame(302, $save->getStatusCode());
        self::assertSame('New title', $app->database()->connection()->table('settings')->value('value'));
    }

    public function testSettingsAreGroupedByPrefix(): void
    {
        [$app, $session] = $this->boot([
            ['name' => 'SITE_TITLE', 'value' => 'Дом', 'title' => 'Название сайта', 'type' => 'text'],
            ['name' => 'MQTT_HOST', 'value' => 'localhost', 'title' => 'Брокер', 'type' => 'text'],
            ['name' => 'BASE_URL', 'value' => '', 'title' => 'Адрес', 'type' => 'text'],
        ]);

        $page = $app->handle(Request::create('/settings', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        $html = (string)$page->getContent();
        self::assertStringContainsString('md-tabs', $html);
        self::assertStringContainsString('/settings?group=MQTT', $html);

        $mqtt = $app->handle(Request::create('/settings', 'GET', ['group' => 'MQTT']));
        $mqttHtml = (string)$mqtt->getContent();
        self::assertStringContainsString('MQTT_HOST', $mqttHtml);
        self::assertStringNotContainsString('SITE_TITLE', $mqttHtml);

        $save = $app->handle(Request::create('/settings/1', 'POST', [
            '_token' => $session->csrfToken(),
            'value' => 'Новое имя',
        ]));
        self::assertSame(302, $save->getStatusCode());
        self::assertStringContainsString('group=SITE', (string)$save->headers->get('Location'));
    }

    /**
     * @param list<array{name:string,value:string,title:string,type:string}> $rows
     * @return array{0:Application,1:ArraySession}
     */
    private function boot(array $rows): array
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
        $app->database()->schema()->create('settings', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->text('value')->nullable();
            $table->string('title')->default('');
            $table->string('type')->default('text');
            $table->timestamps();
            $table->softDeletes();
        });

        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $app->database()->connection()->table('settings')->insert($row + [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        SessionFixture::authenticate($app);

        return [$app, $session];
    }
}
