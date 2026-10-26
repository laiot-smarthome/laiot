<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\KnowledgeBase\KnowledgeBaseController;

return static function (Router $router): void {
    $router->get('/', [KnowledgeBaseController::class, 'index'])->name('index');
    $router->get('/{doc}', [KnowledgeBaseController::class, 'show'])
        ->where('doc', '[A-Za-z0-9_./-]+')
        ->name('show');
};
