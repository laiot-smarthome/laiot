<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use LaIOT\Foundation\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'queue:retry', description: 'Вернуть упавшее задание в очередь')]
final class QueueRetryCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'ID задания');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (int)$input->getArgument('id');
        if (!$this->app->queue()->retry($id)) {
            $output->writeln('<error>Упавшее задание не найдено: ' . $id . '</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>Задание ' . $id . ' возвращено в очередь.</info>');

        return Command::SUCCESS;
    }
}
