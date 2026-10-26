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

#[AsCommand(name: 'module:remove', description: 'Удалить сторонний модуль из реестра')]
final class ModuleRemoveCommand extends Command
{
    public function __construct(private readonly ModuleInstaller $installer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'ID модуля');
        $this->setHelp('Файлы модуля и его таблицы не удаляются: это отдельное решение владельца.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->installer->remove((string)$input->getArgument('id'));
            $output->writeln('<info>Модуль удалён из реестра.</info>');

            return Command::SUCCESS;
        } catch (Throwable $error) {
            $output->writeln('<error>' . $error->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
