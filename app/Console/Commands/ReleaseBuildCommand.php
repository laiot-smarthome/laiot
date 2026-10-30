<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use FilesystemIterator;
use LaIOT\Services\Modules\ModuleManifest;
use LaIOT\Services\Updates\ReleaseLayout;
use LaIOT\Services\Updates\ReleaseManifest;
use LaIOT\Services\Updates\UpdateFiles;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Поставка строится из чистого checkout CI после проверки и production Composer install.
 * 2026-10-30 — несовместимость встроенного модуля с номером выпуска останавливает сборку до упаковки.
 * 2026-10-29 — готовый zip с vendor и статикой, без .env, var и сторонних модулей.
 */
#[AsCommand(name: 'release:build', description: 'Собрать готовый номерной релиз LaIOT')]
final class ReleaseBuildCommand extends Command
{
    public function __construct(private readonly string $root)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('tag', InputArgument::REQUIRED, 'Тег main-X.Y.Z или beta-X.Y.Z-beta.N');
        $this->addOption('output', null, InputOption::VALUE_REQUIRED, 'Каталог готового архива и метаданных', 'var/release');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (string)$input->getArgument('tag');
        ReleaseManifest::assertId($id);
        $channel = str_starts_with($id, 'main-') ? 'main' : 'beta';
        $version = substr($id, strlen($channel) + 1);
        $git = new Process(['git', 'rev-parse', 'HEAD'], $this->root);
        $git->mustRun();
        $modules = [];
        foreach (glob($this->root . '/modules/core/*/module.json') ?: [] as $file) {
            $module = ModuleManifest::fromFile($file, 'modules/core/' . basename(dirname($file)), 'core');
            $module->assertCompatible($version, PHP_VERSION);
            $modules[$module->id()] = $module->data;
        }
        $lock = UpdateFiles::read($this->root . '/composer.lock');
        $requirements = ['>=8.2'];
        $extensions = ['pdo_mysql', 'mbstring', 'zip', 'phar', 'openssl', 'pcntl', 'posix'];
        foreach ((array)($lock['packages'] ?? []) as $package) {
            foreach ((array)($package['require'] ?? []) as $name => $constraint) {
                if ($name === 'php') {
                    $requirements[] = $constraint;
                } elseif (str_starts_with($name, 'ext-')) {
                    $extensions[] = substr($name, 4);
                }
            }
        }
        $manifest = new ReleaseManifest([
            'id' => $id, 'version' => $version, 'channel' => $channel,
            'commit' => trim($git->getOutput()), 'php' => '>=8.2', 'php_requirements' => array_values(array_unique($requirements)),
            'extensions' => array_values(array_unique($extensions)), 'modules' => $modules, 'built_at' => gmdate('c'),
        ]);
        $destination = (string)$input->getOption('output');
        UpdateFiles::directory($destination);
        if (!is_file($this->root . '/public/assets/admin/laiot.css') || !is_file($this->root . '/vendor/autoload.php')) {
            throw new RuntimeException('Перед выпуском установите production-зависимости и соберите frontend.');
        }
        $archive = $destination . '/laiot.zip';
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Не удалось создать архив.');
        }
        foreach (array_merge(ReleaseLayout::CONTENT, ['modules/core']) as $name) {
            $path = $this->root . '/' . $name;
            if (!file_exists($path)) {
                continue;
            }
            if (is_file($path)) {
                $zip->addFile($path, $name);
                continue;
            }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isLink()) {
                    throw new RuntimeException('Checkout содержит символическую ссылку в поставке.');
                }
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($this->root) + 1));
                if (!str_starts_with($relative, 'public/assets/modules/')) {
                    $zip->addFile($file->getPathname(), $relative);
                }
            }
        }
        $zip->addFromString('release.json', json_encode($manifest->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if (!$zip->close()) {
            throw new RuntimeException('Не удалось завершить архив.');
        }
        UpdateFiles::json($destination . '/laiot-release.json', $manifest->data + ['sha256' => hash_file('sha256', $archive), 'size' => filesize($archive)]);
        $output->writeln('Готово: ' . $destination);
        return self::SUCCESS;
    }
}
