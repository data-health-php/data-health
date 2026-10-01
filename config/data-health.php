<?php

declare(strict_types=1);

return [

    'scheduler' => [
        'enabled' => env('DATA_HEALTH_SCHEDULER_ENABLED', true),
    ],

    'auto_delete' => [
        'enabled' => env('DATA_HEALTH_AUTO_DELETE_ENABLED', false),
    ],

    'directories' => [
        'app/DataHealth' => 'App\\DataHealth\\',
    ],

];
