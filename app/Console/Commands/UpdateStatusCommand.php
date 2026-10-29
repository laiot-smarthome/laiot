<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use LaIOT\Services\Updates\UpdateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Диагностика доступна даже при незавершённом восстановлении MySQL.
 * 2026-10-29 — состояние операции и путь ключа без вывода самого секрета.
 */
#[AsCommand(name: 'update:status', description: 'Показать состояние обновления LaIOT без подключения к БД')]
final class UpdateStatusCommand extends Command
{
    public function __construct(private readonly UpdateService $updates)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $state = $this->updates->state->read();
        $output->writeln((string)($state['status'] ?? 'Операций пока нет.'));
        $output->writeln((string)($state['message'] ?? ''));
        $output->writeln('Восстановление: /admin/updates/recovery; ключ: ' . $this->updates->state->directory . '/recovery.key');
        return self::SUCCESS;
    }
}
