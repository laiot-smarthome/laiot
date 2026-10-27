<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use MajorDoMo\Modules\ModuleAssets;
use PHPUnit\Framework\TestCase;

/**
 * Публикация и удаление статики модуля.
 *
 * 2026-10-27 — тест создан вместе с ModuleAssets.
 */
final class ModuleAssetsTest extends TestCase
{
    private string $root;
    private string $module;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mydomo-assets-' . bin2hex(random_bytes(4));
        $this->module = $this->root . '/modules/demo';
        mkdir($this->module . '/assets/css', 0775, true);
        file_put_contents($this->module . '/assets/css/app.css', 'body{}');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->root);
        parent::tearDown();
    }

    public function testPublishCopiesAssetsUnderPublicModules(): void
    {
        $assets = new ModuleAssets($this->root);

        self::assertSame(1, $assets->publish('demo', $this->module));

        $target = $this->root . '/public/assets/modules/demo/css/app.css';
        self::assertFileExists($target);
        self::assertSame('body{}', (string)file_get_contents($target));
    }

    public function testRemoveDeletesPublishedDirectory(): void
    {
        $assets = new ModuleAssets($this->root);
        $assets->publish('demo', $this->module);

        self::assertTrue($assets->remove('demo'));
        self::assertDirectoryDoesNotExist($this->root . '/public/assets/modules/demo');
    }

    public function testMissingAssetsIsNotAnError(): void
    {
        $assets = new ModuleAssets($this->root);
        mkdir($this->root . '/modules/empty');

        self::assertSame(0, $assets->publish('empty', $this->root . '/modules/empty'));
    }
}
