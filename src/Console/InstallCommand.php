<?php

declare(strict_types=1);

namespace MajorDoMo\Console;

use MajorDoMo\Install\InstallCheck;
use MajorDoMo\Install\InstallException;
use MajorDoMo\Install\InstallRequest;
use MajorDoMo\Install\InstallService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Установка MyDoMo из командной строки.
 *
 * Команда делает то же, что страница `/install`: проверяет окружение,
 * подключения, пишет `.env`, выполняет миграции и создаёт администратора.
 * Пароль передаётся опцией и остаётся в истории оболочки — для сервера
 * предпочтительнее страница установки, команда нужна для восстановления
 * доступа и автоматизации.
 *
 * 2026-10-28 — установщик нового ядра.
 */
#[AsCommand(name: 'install', description: 'Установить MyDoMo: база, кэш и администратор')]
final class InstallCommand extends Command
{
    public function __construct(private readonly InstallService $installer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('db-host', null, InputOption::VALUE_REQUIRED, 'Сервер MySQL/MariaDB', '127.0.0.1')
            ->addOption('db-port', null, InputOption::VALUE_REQUIRED, 'Порт базы данных', '3306')
            ->addOption('db-name', null, InputOption::VALUE_REQUIRED, 'Имя базы данных')
            ->addOption('db-user', null, InputOption::VALUE_REQUIRED, 'Пользователь базы данных')
            ->addOption('db-password', null, InputOption::VALUE_REQUIRED, 'Пароль базы данных')
            ->addOption('db-create', null, InputOption::VALUE_NONE, 'Создать базу, если её нет')
            ->addOption('cache-driver', null, InputOption::VALUE_REQUIRED, 'Кэш: redis или file', 'redis')
            ->addOption('redis-host', null, InputOption::VALUE_REQUIRED, 'Сервер Redis', '127.0.0.1')
            ->addOption('redis-port', null, InputOption::VALUE_REQUIRED, 'Порт Redis', '6379')
            ->addOption('redis-db', null, InputOption::VALUE_REQUIRED, 'Номер базы Redis', '0')
            ->addOption('redis-password', null, InputOption::VALUE_REQUIRED, 'Пароль Redis')
            ->addOption('admin-login', null, InputOption::VALUE_REQUIRED, 'Логин администратора')
            ->addOption('admin-name', null, InputOption::VALUE_REQUIRED, 'Имя администратора', 'Администратор')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'Пароль администратора')
            ->addOption('timezone', null, InputOption::VALUE_REQUIRED, 'Часовой пояс', 'Europe/Moscow');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->installer->installed()) {
            $output->writeln('<error>MyDoMo уже установлена: ' . $this->installer->lockPath() . '</error>');

            return Command::FAILURE;
        }

        $request = InstallRequest::fromArray([
            'db_host' => $this->option($input, 'db-host'),
            'db_port' => $this->option($input, 'db-port'),
            'db_name' => $this->option($input, 'db-name'),
            'db_user' => $this->option($input, 'db-user'),
            'db_password' => $this->option($input, 'db-password'),
            'db_create' => (bool)$input->getOption('db-create'),
            'cache_driver' => $this->option($input, 'cache-driver'),
            'redis_host' => $this->option($input, 'redis-host'),
            'redis_port' => $this->option($input, 'redis-port'),
            'redis_database' => $this->option($input, 'redis-db'),
            'redis_password' => $this->option($input, 'redis-password'),
            'admin_login' => $this->option($input, 'admin-login'),
            'admin_name' => $this->option($input, 'admin-name'),
            'admin_password' => $this->option($input, 'admin-password'),
            'timezone' => $this->option($input, 'timezone'),
        ]);

        $errors = $this->installer->validate($request);
        if ($errors !== []) {
            foreach ($errors as $field => $message) {
                $output->writeln('<error>' . $field . ': ' . $message . '</error>');
            }

            return Command::FAILURE;
        }

        $checks = array_merge($this->installer->requirements(), $this->installer->preflight($request));
        $table = new Table($output);
        $table->setHeaders(['Проверка', 'Результат', 'Подробности']);
        foreach ($checks as $check) {
            $table->addRow([$check->label, $this->status($check), $check->message]);
        }
        $table->render();

        if (array_filter($checks, static fn (InstallCheck $check): bool => $check->failed()) !== []) {
            $output->writeln('<error>Установка остановлена: исправьте ошибки выше.</error>');

            return Command::FAILURE;
        }

        try {
            $report = $this->installer->apply($request);
        } catch (InstallException $error) {
            $output->writeln('<error>' . $error->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>MyDoMo ' . $report->version . ' установлена.</info>');
        $output->writeln('Администратор: ' . $report->adminLogin . ' (ID ' . $report->adminId . ')');
        $output->writeln('База: ' . $request->dbName . '; кэш: ' . $report->cacheDriver);
        $output->writeln('Миграций применено: ' . $report->migrationsCount());
        $output->writeln('Признак установки: ' . $this->installer->lockPath());
        foreach ($report->warnings as $warning) {
            $output->writeln('<comment>' . $warning . '</comment>');
        }
        $output->writeln('Дальше: настройте службы по docs/mydomo/deploy.md и откройте /login.');

        return Command::SUCCESS;
    }

    private function option(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);

        return is_string($value) ? trim($value) : '';
    }

    private function status(InstallCheck $check): string
    {
        return match ($check->status) {
            InstallCheck::OK => '<info>ok</info>',
            InstallCheck::WARN => '<comment>внимание</comment>',
            default => '<error>ошибка</error>',
        };
    }
}
