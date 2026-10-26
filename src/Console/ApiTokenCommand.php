<?php

declare(strict_types=1);

namespace MajorDoMo\Console;

use MajorDoMo\Auth\ApiTokenRepository;
use MajorDoMo\Foundation\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'api:token', description: 'Выпустить API-токен пользователя')]
final class ApiTokenCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('user_id', InputArgument::REQUIRED, 'ID пользователя');
        $this->addArgument('name', InputArgument::REQUIRED, 'Имя токена');
        $this->addOption('ttl', null, InputOption::VALUE_REQUIRED, 'Срок жизни в секундах');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ttl = $input->getOption('ttl');
        $repository = $this->app->container()->make(ApiTokenRepository::class);
        $token = $repository->issue(
            (int)$input->getArgument('user_id'),
            (string)$input->getArgument('name'),
            $ttl !== null ? (int)$ttl : null
        );

        $output->writeln('<info>Токен выпущен. Он показывается один раз:</info>');
        $output->writeln($token);

        return Command::SUCCESS;
    }
}
