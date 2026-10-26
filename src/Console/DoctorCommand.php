<?php

declare(strict_types=1);

namespace MajorDoMo\Console;

use MajorDoMo\Foundation\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'doctor', description: 'Проверить окружение MyDoMo')]
final class DoctorCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $checks = [];
        foreach (['pdo_mysql', 'mbstring', 'intl', 'curl', 'json', 'openssl', 'fileinfo'] as $extension) {
            $checks[] = ['PHP extension', $extension, extension_loaded($extension) ? 'ok' : 'missing'];
        }
        $checks[] = ['Timezone', $this->app->clock()->timezone(), 'ok'];

        foreach ([$this->app->root() . '/var', $this->app->root() . '/var/cache', $this->app->root() . '/var/log'] as $path) {
            $checks[] = ['Writable', $path, is_dir($path) && is_writable($path) ? 'ok' : 'check'];
        }

        $checks[] = ['Database', 'ping', $this->app->database()->ping() ? 'ok' : 'unavailable'];
        try {
            $key = 'doctor.' . bin2hex(random_bytes(4));
            $this->app->cache()->put($key, 'ok', 10);
            $cache = $this->app->cache()->get($key) === 'ok';
            $this->app->cache()->forget($key);
        } catch (Throwable) {
            $cache = false;
        }
        $checks[] = ['Cache', 'roundtrip', $cache ? 'ok' : 'failed'];

        $table = new Table($output);
        $table->setHeaders(['Check', 'Name', 'Result']);
        foreach ($checks as $row) {
            $table->addRow($row);
        }
        $table->render();

        $failed = array_filter($checks, static fn (array $row): bool => $row[2] !== 'ok');

        return $failed === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
