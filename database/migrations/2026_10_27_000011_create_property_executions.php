<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use MajorDoMo\Events\PropertyExecutionRepository;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if (!$schema->hasTable('property_executions')) {
        $schema->create('property_executions', PropertyExecutionRepository::schema(...));
    }
    if ($schema->hasTable('queue_jobs') && !$schema->hasColumn('queue_jobs', 'execution_id')) {
        $schema->table('queue_jobs', static function (Blueprint $table): void {
            $table->unsignedBigInteger('execution_id')->nullable()->index();
        });
    }
    if (!$schema->hasTable('queue_jobs')) {
        return;
    }
    // Старые задания сохраняются как самостоятельные цепочки: прежний payload
    // не содержит надёжного идентификатора выполнения родительского задания.
    $connection->table('queue_jobs')->whereNull('execution_id')->whereNull('deleted_at')
        ->whereIn('job', ['property.changed', 'module.property.changed'])
        ->orderBy('id')->chunkById(100, static function ($jobs) use ($connection): void {
            foreach ($jobs as $job) {
                $connection->transaction(static function () use ($connection, $job): void {
                    $row = $connection->table('queue_jobs')->where('id', $job->id)
                        ->whereNull('execution_id')->lockForUpdate()->first();
                    if ($row === null) {
                        return;
                    }
                    $payload = json_decode((string)$row->payload, true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($payload)) {
                        throw new RuntimeException('Payload задания должен быть массивом: ' . $row->id);
                    }
                    $stream = $row->job === 'module.property.changed'
                        ? 'module:' . (string)($payload['module'] ?? '')
                        : 'property:' . (int)($payload['object_id'] ?? 0) . ':' . (int)($payload['property_id'] ?? 0);
                    $now = date('Y-m-d H:i:s');
                    $id = (int)$connection->table('property_executions')->insertGetId([
                        'stream' => $stream, 'created_at' => $now, 'updated_at' => $now,
                        'status' => $row->failed_at === null ? 'pending' : 'error',
                        'failed_at' => $row->failed_at,
                    ]);
                    $connection->table('property_executions')->where('id', $id)->update([
                        'root_id' => $id, 'error_step_id' => $row->failed_at === null ? 0 : $id,
                    ]);
                    $payload['execution_id'] = $id;
                    $connection->table('queue_jobs')->where('id', $row->id)->update([
                        'execution_id' => $id,
                        'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ]);
                });
            }
        });
};
