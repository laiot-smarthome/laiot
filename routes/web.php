<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Api\Webhooks\WebhookController;
use MajorDoMo\Http\Controllers\ApiDocsController;
use MajorDoMo\Http\Controllers\Core\ActionsLogController;
use MajorDoMo\Http\Controllers\Core\ClassesController;
use MajorDoMo\Http\Controllers\Core\EventsController;
use MajorDoMo\Http\Controllers\Core\JobsController;
use MajorDoMo\Http\Controllers\Core\KnowledgeBaseController;
use MajorDoMo\Http\Controllers\Core\LocationsController;
use MajorDoMo\Http\Controllers\Core\MethodsController;
use MajorDoMo\Http\Controllers\Core\NotificationsPageController;
use MajorDoMo\Http\Controllers\Core\ObjectsController;
use MajorDoMo\Http\Controllers\Core\PropertiesController;
use MajorDoMo\Http\Controllers\Core\ScriptsController;
use MajorDoMo\Http\Controllers\Core\SettingsController;
use MajorDoMo\Http\Controllers\Core\SystemErrorsController;
use MajorDoMo\Http\Controllers\Core\UiKitController;
use MajorDoMo\Http\Controllers\Core\UsersController;
use MajorDoMo\Http\Controllers\DashboardController;
use MajorDoMo\Http\Controllers\HealthController;
use MajorDoMo\Http\Controllers\LoginController;
use MajorDoMo\Http\Controllers\MaintenanceController;
use MajorDoMo\Http\Controllers\ModuleSettingsController;
use MajorDoMo\Http\Controllers\ModulesPageController;
use MajorDoMo\Http\Controllers\ProfileController;
use MajorDoMo\Http\Controllers\RolesController;
use MajorDoMo\Http\Controllers\StatusController;
use MajorDoMo\Http\Controllers\TokensController;
use MajorDoMo\Http\Controllers\YoyoController;
use MajorDoMo\Http\Controllers\YoyoDemoController;

return static function (Router $router): void {
    $router->middleware(['request-id'])->group(function (Router $router): void {
        // Корень ведёт в новый вход; legacy-панели в MyDoMo нет.
        $router->get('/', [LoginController::class, 'show'])->name('home');
        $router->get('/health', [HealthController::class, '__invoke'])->name('health');
        $router->get('/admin', DashboardController::class)
            ->middleware(['auth'])
            ->name('admin.dashboard');
        $router->get('/admin/modules', ModulesPageController::class)
            ->middleware(['auth'])
            ->name('admin.modules');
        foreach (['enable', 'disable', 'install', 'remove'] as $moduleAction) {
            $router->post('/admin/modules/{id}/' . $moduleAction, [ModulesPageController::class, $moduleAction])
                ->where('id', '[a-z0-9_]+')
                ->middleware(['auth', 'can:settings.manage', 'csrf'])
                ->name('admin.modules.' . $moduleAction);
        }
        $router->get('/admin/modules/{id}/settings', [ModuleSettingsController::class, 'show'])
            ->where('id', '[a-z0-9_]+')
            ->middleware(['auth', 'can:settings.manage'])
            ->name('admin.modules.settings');
        $router->post('/admin/modules/{id}/settings', [ModuleSettingsController::class, 'save'])
            ->where('id', '[a-z0-9_]+')
            ->middleware(['auth', 'can:settings.manage', 'csrf'])
            ->name('admin.modules.settings.save');
        $router->post('/admin/modules/{id}/subscriptions', [ModuleSettingsController::class, 'saveSubscriptions'])
            ->where('id', '[a-z0-9_]+')
            ->middleware(['auth', 'can:settings.manage', 'csrf'])
            ->name('admin.modules.subscriptions');
        $router->get('/admin/api', ApiDocsController::class)
            ->middleware(['auth'])
            ->name('admin.api');
        $router->get('/admin/maintenance', [MaintenanceController::class, 'show'])
            ->middleware(['auth', 'can:settings.manage'])
            ->name('admin.maintenance');
        $router->post('/admin/maintenance/enable', [MaintenanceController::class, 'enable'])
            ->middleware(['auth', 'can:settings.manage', 'csrf'])
            ->name('admin.maintenance.enable');
        $router->post('/admin/maintenance/disable', [MaintenanceController::class, 'disable'])
            ->middleware(['auth', 'can:settings.manage', 'csrf'])
            ->name('admin.maintenance.disable');
        $router->get('/admin/profile', [ProfileController::class, 'show'])
            ->middleware(['auth'])
            ->name('admin.profile');
        $router->post('/admin/profile', [ProfileController::class, 'save'])
            ->middleware(['auth', 'csrf'])
            ->name('admin.profile.save');
        $router->get('/status', StatusController::class)
            ->middleware(['auth'])
            ->name('status');
        $router->get('/roles', RolesController::class)
            ->middleware(['auth', 'can:users.manage'])
            ->name('roles');
        $router->get('/admin/tokens', [TokensController::class, 'index'])
            ->middleware(['auth', 'can:users.manage'])
            ->name('admin.tokens');
        $router->post('/admin/tokens', [TokensController::class, 'issue'])
            ->middleware(['auth', 'can:users.manage', 'csrf'])
            ->name('admin.tokens.issue');
        $router->post('/admin/tokens/{id}/revoke', [TokensController::class, 'revoke'])
            ->whereNumber('id')
            ->middleware(['auth', 'can:users.manage', 'csrf'])
            ->name('admin.tokens.revoke');

        $router->get('/login', [LoginController::class, 'show'])->name('login');
        $router->post('/login', [LoginController::class, 'login'])
            ->middleware(['csrf', 'throttle.login'])
            ->name('login.attempt');
        $router->post('/logout', [LoginController::class, 'logout'])
            ->middleware(['auth', 'csrf'])
            ->name('logout');

        $router->middleware('auth')->group(function (Router $router): void {
            // Ядровые разделы: не модули, без ModuleDb/lifecycle.
            // Старый каталог объектов больше не отдельный раздел: объекты
            // открываются из класса. URL оставлен только как точка входа.
            $router->get('/objects', [ObjectsController::class, 'index'])->name('objects.index');
            $router->get('/objects/create', [ObjectsController::class, 'create'])->name('objects.create');
            $router->post('/objects/create', [ObjectsController::class, 'store'])->middleware('csrf')->name('objects.store');
            $router->get('/objects/{id}/edit', [ObjectsController::class, 'edit'])->whereNumber('id')->name('objects.edit');
            $router->post('/objects/{id}/edit', [ObjectsController::class, 'update'])->whereNumber('id')->middleware('csrf')->name('objects.update');
            $router->post('/objects/{id}/values', [ObjectsController::class, 'updateValues'])->whereNumber('id')->middleware('csrf')->name('objects.values');
            $router->post('/objects/{id}/delete', [ObjectsController::class, 'delete'])->whereNumber('id')->middleware('csrf')->name('objects.delete');

            $router->get('/classes', [ClassesController::class, 'index'])->name('classes.index');
            $router->get('/classes/create', [ClassesController::class, 'create'])->name('classes.create');
            $router->post('/classes/create', [ClassesController::class, 'store'])->middleware('csrf')->name('classes.store');
            $router->get('/classes/{id}/edit', [ClassesController::class, 'edit'])->whereNumber('id')->name('classes.edit');
            $router->post('/classes/{id}/edit', [ClassesController::class, 'update'])->whereNumber('id')->middleware('csrf')->name('classes.update');
            $router->post('/classes/{id}/delete', [ClassesController::class, 'delete'])->whereNumber('id')->middleware('csrf')->name('classes.delete');

            // Свойства и методы — контекстные формы класса/объекта, без
            // собственных каталогов и пунктов меню.
            $router->get('/properties/create', [PropertiesController::class, 'create'])->name('properties.create');
            $router->post('/properties/create', [PropertiesController::class, 'store'])->middleware('csrf')->name('properties.store');
            $router->get('/properties/{id}/edit', [PropertiesController::class, 'edit'])->whereNumber('id')->name('properties.edit');
            $router->post('/properties/{id}/edit', [PropertiesController::class, 'update'])->whereNumber('id')->middleware('csrf')->name('properties.update');
            $router->post('/properties/{id}/delete', [PropertiesController::class, 'delete'])->whereNumber('id')->middleware('csrf')->name('properties.delete');

            $router->get('/methods/create', [MethodsController::class, 'create'])->name('methods.create');
            $router->post('/methods/create', [MethodsController::class, 'store'])->middleware('csrf')->name('methods.store');
            $router->get('/methods/{id}/edit', [MethodsController::class, 'edit'])->whereNumber('id')->name('methods.edit');
            $router->post('/methods/{id}/edit', [MethodsController::class, 'update'])->whereNumber('id')->middleware('csrf')->name('methods.update');
            $router->post('/methods/{id}/delete', [MethodsController::class, 'delete'])->whereNumber('id')->middleware('csrf')->name('methods.delete');

            $router->get('/locations', [LocationsController::class, 'index'])->name('locations.index');
            $router->get('/locations/create', [LocationsController::class, 'create'])->name('locations.create');
            $router->post('/locations/create', [LocationsController::class, 'store'])->middleware('csrf')->name('locations.store');
            $router->get('/locations/{id}/edit', [LocationsController::class, 'edit'])->whereNumber('id')->name('locations.edit');
            $router->post('/locations/{id}/edit', [LocationsController::class, 'update'])->whereNumber('id')->middleware('csrf')->name('locations.update');
            $router->post('/locations/{id}/delete', [LocationsController::class, 'delete'])->whereNumber('id')->middleware('csrf')->name('locations.delete');

            $router->get('/scripts', [ScriptsController::class, 'index'])->name('scripts.index');
            $router->get('/scripts/export', [ScriptsController::class, 'export'])->name('scripts.export');
            $router->get('/scripts/import', [ScriptsController::class, 'importForm'])->name('scripts.import');
            $router->post('/scripts/import', [ScriptsController::class, 'import'])
                ->middleware('csrf')
                ->name('scripts.import.store');
            $router->post('/scripts/run', [ScriptsController::class, 'run'])
                ->middleware('csrf')
                ->name('scripts.run');
            // Категории сценариев управляются модальным Yoyo-компонентом
            // `script-categories`; отдельных POST-маршрутов раздела нет.
            $router->get('/scripts/create', [ScriptsController::class, 'create'])->name('scripts.create');
            $router->post('/scripts/create', [ScriptsController::class, 'store'])->middleware('csrf')->name('scripts.store');
            $router->get('/scripts/{id}/edit', [ScriptsController::class, 'edit'])->whereNumber('id')->name('scripts.edit');
            $router->post('/scripts/{id}/edit', [ScriptsController::class, 'update'])->whereNumber('id')->middleware('csrf')->name('scripts.update');
            $router->post('/scripts/{id}/delete', [ScriptsController::class, 'delete'])->whereNumber('id')->middleware('csrf')->name('scripts.delete');

            $router->get('/events', [EventsController::class, 'index'])->name('events.index');

            $router->get('/jobs', [JobsController::class, 'index'])->name('jobs.index');
            $router->post('/jobs/failed/{id}/retry', [JobsController::class, 'retry'])->whereNumber('id')->middleware('csrf')->name('jobs.retry');
            $router->post('/jobs/failed/{id}/forget', [JobsController::class, 'forget'])->whereNumber('id')->middleware('csrf')->name('jobs.forget');

            $router->get('/knowledge-base', [KnowledgeBaseController::class, 'index'])->name('knowledge_base.index');
            $router->get('/knowledge-base/{doc}', [KnowledgeBaseController::class, 'show'])->where('doc', '.*')->name('knowledge_base.show');

            $router->get('/users', [UsersController::class, 'index'])->name('users.index');
            $router->get('/users/create', [UsersController::class, 'create'])->name('users.create');
            $router->post('/users/create', [UsersController::class, 'store'])->middleware('csrf')->name('users.store');
            $router->get('/users/{id}/edit', [UsersController::class, 'edit'])->whereNumber('id')->name('users.edit');
            $router->post('/users/{id}/edit', [UsersController::class, 'update'])->whereNumber('id')->middleware('csrf')->name('users.update');
            $router->post('/users/{id}/delete', [UsersController::class, 'delete'])->whereNumber('id')->middleware('csrf')->name('users.delete');

            $router->get('/settings', [SettingsController::class, 'index'])->name('settings.index');
            $router->get('/settings/{id}/edit', [SettingsController::class, 'edit'])->whereNumber('id')->name('settings.edit');
            $router->post('/settings/{id}', [SettingsController::class, 'save'])->whereNumber('id')->middleware('csrf')->name('settings.save');

            $router->get('/journal', [ActionsLogController::class, 'index'])->name('actions_log.index');
            $router->post('/journal/{id}/delete', [ActionsLogController::class, 'delete'])->whereNumber('id')->middleware('csrf')->name('actions_log.delete');

            $router->get('/errors', [SystemErrorsController::class, 'index'])->name('system_errors.index');
            $router->get('/errors/{id}/file', [SystemErrorsController::class, 'file'])->whereNumber('id')->name('system_errors.file');
            $router->post('/errors/group-delete', [SystemErrorsController::class, 'deleteGroup'])
                ->middleware('csrf')
                ->name('system_errors.delete_group');
            $router->post('/errors/{id}/delete', [SystemErrorsController::class, 'delete'])->whereNumber('id')->middleware('csrf')->name('system_errors.delete');

            $router->get('/ui-kit', [UiKitController::class, 'index'])->name('ui_kit.index');
            $router->get('/notifications', [NotificationsPageController::class, 'index'])->name('notifications.index');
            $router->post('/notifications/{id}/dismiss', [NotificationsPageController::class, 'dismiss'])->whereNumber('id')->middleware('csrf')->name('notifications.dismiss');

            $router->get('/api', [WebhookController::class, 'index'])->name('api.index');
            $router->get('/api/create', [WebhookController::class, 'create'])->name('api.create');
            $router->post('/api', [WebhookController::class, 'store'])->middleware('csrf')->name('api.store');
            $router->get('/api/{id}/edit', [WebhookController::class, 'edit'])->whereNumber('id')->name('api.edit');
            $router->post('/api/{id}', [WebhookController::class, 'update'])->whereNumber('id')->middleware('csrf')->name('api.update');
            $router->post('/api/{id}/delete', [WebhookController::class, 'destroy'])->whereNumber('id')->middleware('csrf')->name('api.delete');
            $router->post('/api/{id}/rotate', [WebhookController::class, 'rotate'])->whereNumber('id')->middleware('csrf')->name('api.rotate');

            $router->get('/yoyo-demo', [YoyoDemoController::class, '__invoke'])->name('yoyo.demo');
            // Yoyo/htmx обновляет компонент GET-запросом. Маршрут только для
            // auth-сессии; изменяющие Yoyo-компоненты должны сами проверять
            // право/идемпотентность, POST-вариант оставлен с CSRF.
            $router->get('/yoyo', [YoyoController::class, '__invoke'])
                ->middleware('auth')
                ->name('yoyo.render');
            $router->post('/yoyo', [YoyoController::class, '__invoke'])
                ->middleware('csrf')
                ->name('yoyo.update');
        });
    });
};
