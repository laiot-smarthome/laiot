<?php

declare(strict_types=1);

namespace MajorDoMo\Console;

use MajorDoMo\Foundation\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'module:list', description: 'Показать core-модули MyDoMo')]
final class ModuleListCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $modules = $this->app->modules()->all();
        if ($modules === []) {
            $output->writeln('<comment>Core-модули не найдены.</comment>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Type', 'Version', 'Path']);
        foreach ($modules as $module) {
            $table->addRow([
                (string)$module['id'],
                (string)($module['type'] ?? 'core'),
                (string)($module['version'] ?? '0.0.0'),
                (string)($module['path'] ?? ''),
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
