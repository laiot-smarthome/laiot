<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use LaIOT\Services\Auth\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Создание учётной записи с объектом Users и стандартной ролью.
 *
 * 2026-10-27 — справка отражает общие правила логина и пароля.
 */
#[AsCommand(name: 'user:create', description: 'Создать пользователя LaIOT')]
final class UserCreateCommand extends Command
{
    public function __construct(private readonly UserRepository $users)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED, 'Логин: 8–64 символа, латиница, цифры и _, первый — буква или _');
        $this->addArgument('name', InputArgument::REQUIRED, 'Имя');
        $this->addArgument('password', InputArgument::REQUIRED, 'Пароль: минимум 8 символов, включая специальный символ');
        $this->addOption('admin', null, InputOption::VALUE_NONE, 'Сделать администратором');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $id = $this->users->create(
                (string)$input->getArgument('username'),
                (string)$input->getArgument('name'),
                (string)$input->getArgument('password'),
                (bool)$input->getOption('admin')
            );
        } catch (\Throwable $error) {
            $output->writeln('<error>' . $error->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>Пользователь создан: ID ' . $id . '</info>');

        return Command::SUCCESS;
    }
}
