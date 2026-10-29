<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use LaIOT\Database\Migrator;
use LaIOT\Foundation\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'db:migrate', description: 'Применить миграции LaIOT')]
final class MigrateCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $migrator = new Migrator($this->app->database(), $this->app->root() . '/database/migrations');
        $ran = $migrator->migrate();

        if ($ran === []) {
            $output->writeln('<comment>Новых миграций нет.</comment>');

            return Command::SUCCESS;
        }

        foreach ($ran as $name) {
            $output->writeln('<info>Применена: ' . $name . '</info>');
        }

        return Command::SUCCESS;
    }
}
