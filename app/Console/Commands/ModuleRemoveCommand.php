<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use LaIOT\Services\Modules\ModuleInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Удаление стороннего модуля с очисткой его файлов и опциональным purge БД.
 *
 * Изменения:
 * 2026-10-28 — справка предупреждает об удалении файлов без --purge.
 */
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
        $this->addOption('purge', null, InputOption::VALUE_NONE, 'Удалить таблицы модуля и SQLite-данные');
        $this->setHelp('Файлы var/files/modules/<id> удаляются всегда. Таблицы и SQLite/data-каталог сохраняются, если не указан --purge; код модуля остаётся.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->installer->remove(
                (string)$input->getArgument('id'),
                (bool)$input->getOption('purge')
            );
            $output->writeln('<info>Модуль удалён из реестра.</info>');
            if ((bool)$input->getOption('purge')) {
                $output->writeln('<info>Таблицы и данные модуля удалены.</info>');
            }

            return Command::SUCCESS;
        } catch (Throwable $error) {
            $output->writeln('<error>' . $error->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
