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

#[AsCommand(name: 'queue:stats', description: 'Показать состояние очереди LaIOT')]
final class QueueStatsCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('queue', null, InputOption::VALUE_REQUIRED, 'Имя очереди', 'default');
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Вывести JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queue = (string)$input->getOption('queue');
        $stats = $this->app->queue()->stats($queue);

        if ($input->getOption('json')) {
            $output->writeln((string)json_encode(['queue' => $queue] + $stats, JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Очередь', 'Pending', 'Reserved', 'Failed']);
        $table->addRow([$queue, $stats['pending'], $stats['reserved'], $stats['failed']]);
        $table->render();

        return Command::SUCCESS;
    }
}
