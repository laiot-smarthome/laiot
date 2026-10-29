<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use LaIOT\Foundation\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'queue:failed', description: 'Показать упавшие задания LaIOT')]
final class QueueFailedCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('queue', null, InputOption::VALUE_REQUIRED, 'Имя очереди', 'default');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Сколько записей', '50');
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Вывести JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queue = (string)$input->getOption('queue');
        $rows = $this->app->queue()->failed($queue, (int)$input->getOption('limit'));

        if ($input->getOption('json')) {
            $output->writeln((string)json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        if ($rows === []) {
            $output->writeln('<comment>Упавших заданий нет.</comment>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Job', 'Attempts', 'Failed at', 'Error']);
        foreach ($rows as $row) {
            $table->addRow([
                $row['id'],
                $row['job'],
                $row['attempts'],
                $row['failed_at'],
                mb_substr($row['error'], 0, 80),
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
