<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use LaIOT\Services\Modules\ModuleChecker;
use LaIOT\Services\Modules\ModuleManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'module:check', description: 'Найти legacy-вызовы в коде модулей')]
final class ModuleCheckCommand extends Command
{
    public function __construct(
        private readonly ModuleManager $modules,
        private readonly ModuleChecker $checker
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, 'ID модуля; без него проверяются все');
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Вывести результат в JSON');
        $this->setHelp('Ищет SQLSelect*, $db, eval, shell, PDO/mysqli в src, routes и helpers модуля.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = trim((string)$input->getArgument('id'));
        $modules = $this->modules->discovered();
        if ($id !== '' && !isset($modules[$id])) {
            $output->writeln('<error>Модуль не найден: ' . $id . '</error>');

            return Command::FAILURE;
        }

        $result = [];
        foreach ($id !== '' ? [$id] : array_keys($modules) as $moduleId) {
            $findings = $this->checker->scanModule($modules[$moduleId]);
            $result[$moduleId] = $findings;
        }

        if ((bool)$input->getOption('json')) {
            $output->writeln((string)json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            foreach ($result as $moduleId => $findings) {
                foreach ($findings as $finding) {
                    $output->writeln(
                        '<error>' . $moduleId . '</error> ' . $finding['file']
                        . ':' . $finding['line'] . ' [' . $finding['rule'] . '] ' . $finding['snippet']
                    );
                }
                if ($findings === []) {
                    $output->writeln('<info>ok</info> ' . $moduleId);
                }
            }
        }

        $total = array_sum(array_map('count', $result));
        if (!$output->isQuiet()) {
            $output->writeln('Проверено модулей: ' . count($result) . ', найдено: ' . $total);
        }

        return $total === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
