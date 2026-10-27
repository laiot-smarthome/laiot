<?php

declare(strict_types=1);

namespace MajorDoMo\Console;

use MajorDoMo\Foundation\Application;
use MajorDoMo\Logging\ErrorRepository;
use MajorDoMo\Logging\LoggerFactory;
use MajorDoMo\Queue\QueueWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'queue:work', description: 'Обработать задания очереди MyDoMo')]
/** 2026-10-27 — ошибки разового worker записываются в общий журнал ошибок. */
final class QueueWorkCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('queue', null, InputOption::VALUE_REQUIRED, 'Имя очереди', 'default');
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Обработать не более одного задания');
        $this->addOption('max-jobs', null, InputOption::VALUE_REQUIRED, 'Максимум заданий, 0 — до пустой очереди', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queueName = (string)$input->getOption('queue');
        $maxJobs = (bool)$input->getOption('once') ? 1 : max(0, (int)$input->getOption('max-jobs'));
        $worker = new QueueWorker(
            $this->app->queue(),
            $this->app->jobs(),
            $this->app->container()->make(LoggerFactory::class)->channel('queue'),
            $this->app->container()->make(ErrorRepository::class)
        );
        $processed = 0;

        while ($maxJobs === 0 || $processed < $maxJobs) {
            if (!$worker->work($queueName)) {
                break;
            }
            $processed++;
        }

        $output->writeln($processed > 0
            ? '<info>Обработано заданий: ' . $processed . '</info>'
            : '<comment>Очередь пуста.</comment>');

        return Command::SUCCESS;
    }
}
