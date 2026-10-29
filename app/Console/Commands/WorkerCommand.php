<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use LaIOT\Services\Worker\Worker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'worker', description: 'Запустить фоновые задачи модуля')]
final class WorkerCommand extends Command
{
    public function __construct(private readonly Worker $worker)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('module', InputArgument::REQUIRED, 'ID модуля');
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Выполнить все задачи один раз и выйти');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $module = (string)$input->getArgument('module');
        try {
            $this->worker->run($module, (bool)$input->getOption('once'));
            $output->writeln('<info>Worker завершён:</info> ' . $module);

            return Command::SUCCESS;
        } catch (Throwable $error) {
            $output->writeln('<error>' . $error->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
