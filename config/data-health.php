<?php

declare(strict_types=1);

return [

    'scheduler' => [
        'enabled' => env('DATA_HEALTH_SCHEDULER_ENABLED', true),
    ],

    'directories' => [
        'app/DataHealth' => 'App\\DataHealth\\',
    ],

];
