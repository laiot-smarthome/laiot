<?php

declare(strict_types=1);

namespace MajorDoMo\Console;

use MajorDoMo\Worker\CoreWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'core-worker', description: 'Критичный ядровой процесс (queue/events/timeouts)')]
final class CoreWorkerCommand extends Command
{
    public function __construct(private readonly CoreWorker $worker)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('task', InputArgument::REQUIRED, 'queue, events или timeouts');
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Один проход и выход');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->worker->run((string)$input->getArgument('task'), (bool)$input->getOption('once'));
            $output->writeln('<info>Core-worker завершён.</info>');

            return Command::SUCCESS;
        } catch (Throwable $error) {
            $output->writeln('<error>' . $error->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
