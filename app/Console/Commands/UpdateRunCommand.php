<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use LaIOT\Services\Updates\UpdateRunner;
use LaIOT\Services\Updates\UpdateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Отдельная служба выполняет только уже подтверждённую операцию из файлового журнала.
 * 2026-10-29 — запуск без boot потенциально повреждённого приложения.
 */
#[AsCommand(name: 'update:run', description: 'Выполнить подтверждённое обновление или восстановление LaIOT')]
final class UpdateRunCommand extends Command
{
    public function __construct(private readonly UpdateService $updates)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            (new UpdateRunner($this->updates))->run();
            $output->writeln('<info>Операция завершена.</info>');
            return self::SUCCESS;
        } catch (Throwable $error) {
            $output->writeln('<error>' . htmlspecialchars($error->getMessage(), ENT_QUOTES) . '</error>');
            return self::FAILURE;
        }
    }
}
