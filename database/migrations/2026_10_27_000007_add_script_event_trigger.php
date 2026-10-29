<?php

declare(strict_types=1);

namespace LaIOT\Scripts;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Connection;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if (!$schema->hasTable('scripts')) {
        return;
    }

    // Признак «запускать по расписанию» заменён системным событием: расписания
    // в ядре нет, а событие сценарий может выбрать сам.
    if ($schema->hasColumn('scripts', 'run_periodically')) {
        $schema->table('scripts', static function (Blueprint $table): void {
            $table->dropColumn('run_periodically');
        });
    }

    if ($schema->hasColumn('scripts', 'event_name')) {
        return;
    }

    $schema->table('scripts', static function (Blueprint $table): void {
        $table->string('event_name', 191)->default('')->after('category_id');
        $table->index('event_name');
    });
};
