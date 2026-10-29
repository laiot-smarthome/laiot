<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use LaIOT\Database\DatabaseManager;
use LaIOT\Database\Migrator;
use LaIOT\Foundation\Application;
use LaIOT\Services\Modules\ModuleAssets;
use LaIOT\Services\Modules\ModuleManager;
use LaIOT\Services\Updates\UpdateState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Миграции предшествуют загрузке новых модулей; полная проверка выполняется отдельным PHP-процессом.
 * 2026-10-29 — проверка зависимостей, схемы, администратора, шифрования и кэша.
 */
#[AsCommand(name: 'update:probe', description: 'Проверить подготовленный релиз LaIOT')]
final class UpdateProbeCommand extends Command
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly string $root, private readonly array $config)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('migrate', null, InputOption::VALUE_NONE, 'Применить миграции до проверки');
        $this->addOption('platform-only', null, InputOption::VALUE_NONE, 'Проверить только загрузку зависимостей PHP');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            if ($input->getOption('platform-only')) {
                $output->writeln('Зависимости PHP загружены.');
                return self::SUCCESS;
            }
            $database = new DatabaseManager((array)$this->config['database']);
            if ($input->getOption('migrate')) {
                $state = new UpdateState($this->root . '/var/updates');
                $operation = $state->raw();
                if (!$state->locked() || !is_file($state->directory . '/maintenance.flag') || !($operation['snapshot_ready'] ?? false)) {
                    throw new \RuntimeException('Миграции обновителя требуют закрытой записи, активной операции и завершённого снимка БД.');
                }
                (new Migrator($database, $this->root . '/database/migrations'))->migrate();
            }
            $app = new Application($this->root, $this->config);
            $app->boot();
            $state = new UpdateState($this->root . '/var/updates');
            if (is_file($state->directory . '/maintenance.flag') && $state->locked()) {
                // После возврата БД кэш прежней версии может содержать значения из будущего снимка.
                $app->cache()->clear();
            }
            foreach ($app->container()->make(ModuleManager::class)->discovered() as $id => $module) {
                if ($module['installed']) {
                    (new ModuleAssets($this->root))->publish($id, (string)$module['path'], (string)$module['assets']);
                }
            }
            if (!$app->database()->ping() || !$app->database()->connection()->table('users')->where('is_admin', 1)->whereNull('deleted_at')->exists()) {
                throw new \RuntimeException('База или действующий администратор недоступны.');
            }
            $key = 'update.probe.' . bin2hex(random_bytes(8));
            $app->cache()->put($key, 'ok', 30);
            if ($app->cache()->get($key) !== 'ok') {
                throw new \RuntimeException('Проверка кэша не пройдена.');
            }
            $app->cache()->forget($key);
            foreach ((new Migrator($app->database(), $this->root . '/database/migrations'))->status() as $migration) {
                if (!$migration['applied']) {
                    throw new \RuntimeException('Осталась неприменённая миграция ' . $migration['name']);
                }
            }
            $output->writeln('Ядро, база и кэш отвечают.');
            return self::SUCCESS;
        } catch (Throwable $error) {
            $output->writeln('Проверка релиза: ' . $error->getMessage());
            return self::FAILURE;
        }
    }
}
