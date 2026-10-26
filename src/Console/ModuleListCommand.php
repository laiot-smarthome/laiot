<?php

declare(strict_types=1);

namespace MajorDoMo\Console;

use MajorDoMo\Modules\ModuleInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'module:list', description: 'Показать модули MyDoMo SDK')]
final class ModuleListCommand extends Command
{
    public function __construct(private readonly ModuleInstaller $installer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $modules = $this->installer->list();
        if ($modules === []) {
            $output->writeln('<comment>Модули не найдены.</comment>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Name', 'Type', 'Version', 'Installed', 'Enabled', 'Path']);
        foreach ($modules as $module) {
            $table->addRow([
                $module['id'],
                $module['name'],
                $module['type'],
                $module['version'],
                $module['installed'] ? 'yes' : 'no',
                $module['enabled'] ? 'yes' : 'no',
                $module['path'],
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
