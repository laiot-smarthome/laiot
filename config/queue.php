<?php

declare(strict_types=1);

return [
    'table' => 'queue_jobs',
    'max_attempts' => 3,
    'retry_delay' => 60,
    'reservation_timeout' => 300,
    // Полосы очереди событий: одно свойство всегда попадает в одну полосу,
    // чтобы порядок его изменений сохранялся. Для параллельной обработки
    // запускается `core-worker events.<n>` на каждую полосу.
    'events_lanes' => 4,
];
