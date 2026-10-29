<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use LaIOT\Foundation\Application;
use LaIOT\Foundation\ConfigLoader;
use LaIOT\Services\Settings\SettingsSchema;
use LaIOT\Services\WebSocket\WebSocketConfig;
use LaIOT\Services\WebSocket\WebSocketServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Workerman\Worker;

/**
 * Управление WebSocket-сервером Workerman.
 *
 * Сервер работает отдельным процессом, не запускается из HTTP и не читает
 * входящие сообщения. `start` — постоянно работающий процесс; `stop`,
 * `restart`, `status` и `reload` обслуживает Workerman по pid-файлу.
 *
 * 2026-10-29 — команда websocket и отсоединение БД перед fork.
 */
#[AsCommand(name: 'websocket', description: 'WebSocket-сервер обновлений')]
final class WebSocketCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::OPTIONAL, 'start, stop, restart, reload, status или connections', 'start');
        $this->addOption('daemon', 'd', InputOption::VALUE_NONE, 'Запустить в фоне (Linux)');
        $this->addOption('graceful', 'g', InputOption::VALUE_NONE, 'Плавная остановка или перезагрузка');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = (string)$input->getArgument('action');
        if (!in_array($action, ['start', 'stop', 'restart', 'reload', 'status', 'connections'], true)) {
            $output->writeln('<error>Неизвестное действие: ' . $action . '</error>');

            return Command::FAILURE;
        }

        try {
            $root = $this->app->root();
            $appConfig = array_replace_recursive(
                ConfigLoader::load($root),
                SettingsSchema::environment($root)
            );
            $config = WebSocketConfig::fromArray((array)($appConfig['websocket'] ?? []));
            if (!$config->enabled) {
                $output->writeln('<comment>WebSocket выключен: WEBSOCKET_ENABLED=false.</comment>');

                return Command::FAILURE;
            }
            if (DIRECTORY_SEPARATOR !== '/' && $action !== 'start') {
                $output->writeln('<error>Управление сервером по pid-файлу доступно только на Linux.</error>');

                return Command::FAILURE;
            }

            // Workerman читает действие и режим из глобального argv. Длинную
            // форму --graceful переводим в ожидаемый им -g; остальные
            // аргументы Symfony уже разобрал.
            if ((bool)$input->getOption('graceful')) {
                global $argv;
                if (!in_array('-g', $argv, true)) {
                    $argv[] = '-g';
                }
            }
            // Master Symfony уже мог открыть БД при boot. Дочерние процессы
            // Workerman должны открыть своё соединение после fork.
            $this->app->database()->disconnect();
            $daemon = (bool)$input->getOption('daemon');
            Worker::$daemonize = $daemon;
            WebSocketServer::run($root, $appConfig, $config, $daemon);
        } catch (Throwable $error) {
            $output->writeln('<error>WebSocket: ' . $error->getMessage() . '</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
