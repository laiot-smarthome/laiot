<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use LaIOT\Database\Migrator;
use LaIOT\Foundation\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'db:status', description: 'Показать статус миграций LaIOT')]
final class MigrateStatusCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $migrator = new Migrator($this->app->database(), $this->app->root() . '/database/migrations');
        $table = new Table($output);
        $table->setHeaders(['Migration', 'Applied', 'Batch', 'Ran at']);
        foreach ($migrator->status() as $row) {
            $table->addRow([
                $row['name'],
                $row['applied'] ? 'yes' : 'no',
                $row['batch'] ?? '',
                $row['ran_at'] ?? '',
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
