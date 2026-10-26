<?php

declare(strict_types=1);

return [
    'table' => 'queue_jobs',
    'max_attempts' => 3,
    'retry_delay' => 60,
    'reservation_timeout' => 300,
];
