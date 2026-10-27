<?php

declare(strict_types=1);

namespace MajorDoMo\Console;

use MajorDoMo\Maintenance\RetentionPruner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Ручной запуск retention: то же, что делает `core-worker maintenance`.
 *
 * --dry-run показывает объём без удаления; сроки берутся из config/retention.php.
 *
 * 2026-10-27 — команда создана вместе с retention.
 */
#[AsCommand(name: 'db:prune', description: 'Удалить устаревшие историю, журнал, задания, таймеры и ошибки')]
final class PruneCommand extends Command
{
    public function __construct(private readonly RetentionPruner $pruner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать, сколько будет удалено, но ничего не удалять');
        $this->setHelp('Сроки задаются в config/retention.php. Ошибки удаляются вместе с их файлами.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool)$input->getOption('dry-run');
        $counts = $this->pruner->prune($dryRun);
        $total = array_sum($counts);

        foreach ($counts as $section => $count) {
            $output->writeln(sprintf('%-20s %d', $section, $count));
        }
        $output->writeln($dryRun
            ? 'Будет удалено: ' . $total . ' (dry-run, ничего не изменено).'
            : 'Удалено: ' . $total . '.');

        return Command::SUCCESS;
    }
}
