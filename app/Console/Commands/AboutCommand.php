<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use LaIOT\Foundation\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'about', description: 'Показать версию, окружение и пути LaIOT')]
final class AboutCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>LaIOT ' . (string)$this->app->config('version', 'dev') . '</info>');
        $output->writeln('PHP: ' . PHP_VERSION);
        $output->writeln('Root: ' . $this->app->root());
        $output->writeln('Log: ' . (string)$this->app->config('log', 'php://stderr'));
        $output->writeln('Cache: ' . (string)$this->app->config('cache.driver', 'file'));
        $output->writeln('Core modules: ' . count($this->app->modules()->all()));

        return Command::SUCCESS;
    }
}
