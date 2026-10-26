<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('module_shoutbox_messages')) {
        return;
    }

    $schema->create('module_shoutbox_messages', static function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('member_id')->default(0);
        $table->string('message', 255);
        $table->string('source', 64)->default('panel');
        $table->timestamps();
        $table->softDeletes();
    });
};
