<?php

declare(strict_types=1);

namespace MajorDoMo\Console;

use MajorDoMo\Foundation\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cache:clear', description: 'Очистить namespace кэша MyDoMo')]
final class CacheClearCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->app->cache()->clear();
        $output->writeln('<info>Кэш MyDoMo очищен.</info>');

        return Command::SUCCESS;
    }
}
