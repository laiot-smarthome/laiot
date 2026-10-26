<?php

declare(strict_types=1);

namespace MajorDoMo\Console;

use MajorDoMo\Modules\ModuleInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'module:install', description: 'Установить сторонний модуль')]
final class ModuleInstallCommand extends Command
{
    public function __construct(private readonly ModuleInstaller $installer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'ID модуля из modules/<id>');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (string)$input->getArgument('id');
        try {
            $result = $this->installer->install($id);
            foreach ($result['migrations'] as $migration) {
                $output->writeln('<info>Миграция:</info> ' . $migration['migration']);
            }
            $output->writeln('<info>Модуль установлен и включён:</info> ' . $id);

            return Command::SUCCESS;
        } catch (Throwable $error) {
            $output->writeln('<error>' . $error->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
