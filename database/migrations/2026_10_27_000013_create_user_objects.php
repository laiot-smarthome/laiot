<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

// 2026-10-27 — Users создаётся при развёртывании БД; объект связан с учётной
// записью по id, поэтому смена логина не теряет значения и подписки.
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if (!$schema->hasColumn('users', 'object_id')) {
        $schema->table('users', static function (Blueprint $table): void {
            $table->unsignedInteger('object_id')->nullable()->unique();
        });
    }

    $connection->transaction(static function () use ($connection): void {
        $now = date('Y-m-d H:i:s');
        $classId = $connection->table('classes')->where('title', 'Users')->value('id');
        if ($classId === null) {
            $classId = $connection->table('classes')->insertGetId([
                'title' => 'Users', 'parent_id' => 0, 'description' => 'Пользователи дома',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (['isHome' => 'Находится дома', 'isWifiConnected' => 'Подключён к Wi-Fi'] as $title => $description) {
            if (!$connection->table('properties')->where('class_id', $classId)->where('title', $title)->where('object_id', 0)->exists()) {
                $connection->table('properties')->insert([
                    'class_id' => $classId, 'object_id' => 0, 'title' => $title,
                    'description' => $description, 'data_type' => 'bool', 'keep_history' => 1,
                    'only_new_values' => 1, 'on_change' => '', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        foreach (['arrivedHome' => ['Пользователь пришёл домой', true], 'leftHome' => ['Пользователь ушёл из дома', false]] as $title => [$description, $atHome]) {
            if (!$connection->table('methods')->where('class_id', $classId)->where('title', $title)->where('object_id', 0)->exists()) {
                $connection->table('methods')->insert([
                    'class_id' => $classId, 'object_id' => 0, 'title' => $title,
                    'description' => $description,
                    'code' => 'return $this->set(\'isHome\', ' . ($atHome ? 'true' : 'false') . ');',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    });
};
