<?php

declare(strict_types=1);

namespace MajorDoMo\Console;

use MajorDoMo\Auth\UserRepository;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Maintenance\RetentionPruner;
use MajorDoMo\Modules\ModuleChecker;
use MajorDoMo\Modules\ModuleInstaller;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Runtime\CodeRunner;
use MajorDoMo\Runtime\TimeoutRepository;
use MajorDoMo\Support\MaintenanceMode;
use MajorDoMo\Worker\CoreWorker;
use MajorDoMo\Worker\Worker;
use Symfony\Component\Console\Application as ConsoleApplication;

/**
 * Общий реестр CLI для запуска и проверки справочника без выполнения команд.
 *
 * 2026-10-28 — регистрация вынесена из bin/mydomo, чтобы документация
 * проверялась по тем же определениям аргументов и опций.
 */
final class ConsoleFactory
{
    public static function make(Application $app): ConsoleApplication
    {
        $console = new ConsoleApplication(
            'MyDoMo',
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
        $console->add(new MigrateCommand($app));
        $console->add(new MigrateStatusCommand($app));

        return $console;
    }
}
