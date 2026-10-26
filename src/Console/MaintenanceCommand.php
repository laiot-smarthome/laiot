<?php

declare(strict_types=1);

namespace MajorDoMo\Console;

use MajorDoMo\Support\MaintenanceMode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'maintenance', description: 'Включить/выключить режим обслуживания')]
final class MaintenanceCommand extends Command
{
    public function __construct(private readonly MaintenanceMode $mode)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::REQUIRED, 'on, off или status');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = (string)$input->getArgument('action');
        if ($action === 'on') {
            $this->mode->enable();
            $output->writeln('<info>Режим обслуживания включён.</info>');

            return Command::SUCCESS;
        }
        if ($action === 'off') {
            $this->mode->disable();
            $output->writeln('<info>Режим обслуживания выключен.</info>');

            return Command::SUCCESS;
        }
        if ($action === 'status') {
            $output->writeln($this->mode->enabled() ? 'on' : 'off');

            return Command::SUCCESS;
        }

        $output->writeln('<error>Использование: maintenance on|off|status</error>');

        return Command::FAILURE;
    }
}
