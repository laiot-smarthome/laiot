<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use LaIOT\Foundation\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'health', description: 'Проверить БД и кэш LaIOT')]
final class HealthCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $database = $this->app->database()->ping();
        $output->writeln($database ? '<info>database: ok</info>' : '<error>database: unavailable</error>');

        try {
            $cache = $this->app->cache();
            $key = 'console.health.' . bin2hex(random_bytes(4));
            $cache->put($key, 'ok', 30);
            $cacheOk = $cache->get($key) === 'ok';
            $cache->forget($key);
        } catch (Throwable $error) {
            $cacheOk = false;
            $output->writeln('<comment>cache error: ' . $error->getMessage() . '</comment>');
        }
        $output->writeln($cacheOk ? '<info>cache: ok</info>' : '<error>cache: failed</error>');

        return $database && $cacheOk ? Command::SUCCESS : Command::FAILURE;
    }
}
