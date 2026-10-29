<?php

declare(strict_types=1);

namespace LaIOT\Console;

use LaIOT\Console\Commands\AboutCommand;
use LaIOT\Console\Commands\ApiTokenCommand;
use LaIOT\Console\Commands\CacheClearCommand;
use LaIOT\Console\Commands\CoreWorkerCommand;
use LaIOT\Console\Commands\DoctorCommand;
use LaIOT\Console\Commands\HealthCommand;
use LaIOT\Console\Commands\InstallCommand;
use LaIOT\Console\Commands\MaintenanceCommand;
use LaIOT\Console\Commands\MigrateCommand;
use LaIOT\Console\Commands\MigrateStatusCommand;
use LaIOT\Console\Commands\ModuleCheckCommand;
use LaIOT\Console\Commands\ModuleDisableCommand;
use LaIOT\Console\Commands\ModuleEnableCommand;
use LaIOT\Console\Commands\ModuleInstallCommand;
use LaIOT\Console\Commands\ModuleListCommand;
use LaIOT\Console\Commands\ModuleRemoveCommand;
use LaIOT\Console\Commands\ModuleValidateCommand;
use LaIOT\Console\Commands\PruneCommand;
use LaIOT\Console\Commands\QueueFailedCommand;
use LaIOT\Console\Commands\QueueRetryCommand;
use LaIOT\Console\Commands\QueueStatsCommand;
use LaIOT\Console\Commands\QueueWorkCommand;
use LaIOT\Console\Commands\RouteListCommand;
use LaIOT\Console\Commands\TimeoutRunCommand;
use LaIOT\Console\Commands\UserCreateCommand;
use LaIOT\Console\Commands\WorkerCommand;
use LaIOT\Foundation\Application;
use LaIOT\Services\Auth\UserRepository;
use LaIOT\Services\Install\InstallService;
use LaIOT\Services\Maintenance\RetentionPruner;
use LaIOT\Services\Modules\ModuleChecker;
use LaIOT\Services\Modules\ModuleInstaller;
use LaIOT\Services\Modules\ModuleManager;
use LaIOT\Services\Runtime\CodeRunner;
use LaIOT\Services\Runtime\TimeoutRepository;
use LaIOT\Services\Worker\CoreWorker;
use LaIOT\Services\Worker\Worker;
use LaIOT\Support\MaintenanceMode;
use Symfony\Component\Console\Application as ConsoleApplication;

/**
 * Общий реестр CLI для запуска и проверки справочника без выполнения команд.
 *
 * 2026-10-28 — регистрация вынесена из bin/laiot, чтобы документация
 * проверялась по тем же определениям аргументов и опций.
 */
final class ConsoleFactory
{
    public static function make(Application $app): ConsoleApplication
    {
        $console = new ConsoleApplication(
            'LaIOT',
            (string)$app->config('version', 'dev')
        );
        $console->add(new AboutCommand($app));
        $console->add(new DoctorCommand($app));
        $console->add(new MaintenanceCommand(new MaintenanceMode($app->root())));
        $console->add(new TimeoutRunCommand(
            $app->container()->make(TimeoutRepository::class),
            $app->container()->make(CodeRunner::class)
        ));
        $console->add(new HealthCommand($app));
        $console->add(new CacheClearCommand($app));
        $installer = $app->container()->make(ModuleInstaller::class);
        $console->add(new ModuleListCommand($installer));
        $console->add(new ModuleInstallCommand($installer));
        $console->add(new ModuleEnableCommand($installer));
        $console->add(new ModuleDisableCommand($installer));
        $console->add(new ModuleRemoveCommand($installer));
        $console->add(new ModuleValidateCommand($app->root()));
        $console->add(new ModuleCheckCommand(
            $app->container()->make(ModuleManager::class),
            $app->container()->make(ModuleChecker::class)
        ));
        $console->add(new WorkerCommand($app->container()->make(Worker::class)));
        $console->add(new CoreWorkerCommand($app->container()->make(CoreWorker::class)));
        $console->add(new PruneCommand($app->container()->make(RetentionPruner::class)));
        $console->add(new RouteListCommand($app));
        $console->add(new QueueStatsCommand($app));
        $console->add(new QueueWorkCommand($app));
        $console->add(new QueueFailedCommand($app));
        $console->add(new QueueRetryCommand($app));
        $console->add(new ApiTokenCommand($app));
        $console->add(new UserCreateCommand($app->container()->make(UserRepository::class)));
        $console->add(new InstallCommand($app->container()->make(InstallService::class)));
        $console->add(new MigrateCommand($app));
        $console->add(new MigrateStatusCommand($app));

        return $console;
    }
}
