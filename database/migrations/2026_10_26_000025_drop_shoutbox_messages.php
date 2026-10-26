<?php

declare(strict_types=1);

use Illuminate\Database\Connection;

/**
 * Shoutbox был демо-модулем; его сообщения заменены системными
 * `notifications`. Таблица демо-модуля больше не нужна.
 */
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    $schema->dropIfExists('module_shoutbox_messages');
};
