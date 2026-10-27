<?php

declare(strict_types=1);

namespace MajorDoMo\Console;

use MajorDoMo\Runtime\CodeRunner;
use MajorDoMo\Runtime\TimeoutRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'timeout:run', description: 'Выполнить созревшие setTimeOut')]
final class TimeoutRunCommand extends Command
{
    public function __construct(
        private readonly TimeoutRepository $timeouts,
        private readonly CodeRunner $runner
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = 0;
        foreach ($this->timeouts->claim() as $timeout) {
            try {
                $this->runner->run($timeout['code']);
            } catch (Throwable $error) {
                $this->timeouts->release($timeout['id'], $timeout['token']);
                throw $error;
            }
            $this->timeouts->markExecuted($timeout['id'], $timeout['token']);
            $count++;
        }
        $output->writeln('Выполнено: ' . $count);

        return Command::SUCCESS;
    }
}
