<?php

declare(strict_types=1);

return [
    // В production каждый канал пишет в отдельный файл var/log/<channel>.log
    // с суточной ротацией. Поток log=php://stderr (тесты, CLI) отключает
    // разделение файлов, но имя канала остаётся в записи.
    'directory' => __DIR__ . '/../var/log',
    'max_files' => 14,
    'level' => 'info',
    // Отдельный файл на каждую запись system_errors.
    'error_directory' => __DIR__ . '/../var/log/errors',
];
