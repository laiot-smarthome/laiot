<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use LaIOT\Foundation\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'route:list', description: 'Показать маршруты LaIOT')]
final class RouteListCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Вывести JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];
        foreach ($this->app->router()->getRoutes()->getRoutes() as $route) {
            $rows[] = [
                'methods' => implode('|', $route->methods()),
                'uri' => '/' . ltrim($route->uri(), '/'),
                'name' => $route->getName() ?? '',
                'action' => $route->getActionName(),
            ];
        }

        if ($input->getOption('json')) {
            $output->writeln((string)json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $table = new Table($output);
            $table->setHeaders(['Method', 'URI', 'Name', 'Action']);
            foreach ($rows as $row) {
                $table->addRow([$row['methods'], $row['uri'], $row['name'], $row['action']]);
            }
            $table->render();
        }

        return Command::SUCCESS;
    }
}
