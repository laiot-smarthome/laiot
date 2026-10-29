<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use LaIOT\Services\Modules\ModuleChecker;
use PHPUnit\Framework\TestCase;

/**
 * Сканер модулей на legacy-вызовы.
 *
 * 2026-10-27 — тест создан вместе с module:check.
 */
final class ModuleCheckerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/laiot-check-' . bin2hex(random_bytes(4));
        mkdir($this->directory . '/src', 0775, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function testFindsLegacyCalls(): void
    {
        $file = $this->directory . '/src/Legacy.php';
        file_put_contents($file, <<<'PHP'
<?php
SQLSelect("SELECT * FROM x");
$db->query("SELECT 1");
eval('1;');
new PDO('sqlite::memory:');
shell_exec('ls');
PHP);

        $found = array_column((new ModuleChecker())->scanFile($file), 'rule');

        self::assertContains('sqlselect()', $found);
        self::assertContains('$db', $found);
        self::assertContains('eval', $found);
        self::assertContains('PDO', $found);
        self::assertContains('shell_exec', $found);
    }

    public function testCleanModuleHasNoFindings(): void
    {
        file_put_contents($this->directory . '/src/Clean.php', '<?php return 42;');
        $module = [
            'id' => 'clean',
            'path' => $this->directory,
            'src' => 'src',
            'routes' => null,
            'helpers' => null,
        ];

        self::assertSame([], (new ModuleChecker())->scanModule($module));
    }

    public function testFindsLegacyInRoutesFile(): void
    {
        file_put_contents($this->directory . '/routes.php', "<?php\nSQLExec('DELETE FROM x');\n");
        $module = [
            'id' => 'legacy',
            'path' => $this->directory,
            'src' => 'src',
            'routes' => 'routes.php',
            'helpers' => null,
        ];

        $rules = array_column((new ModuleChecker())->scanModule($module), 'rule');
        self::assertContains('sqlexec()', $rules);
    }
}
