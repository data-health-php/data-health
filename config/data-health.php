<?php

declare(strict_types=1);

use DataHealth\Contracts\CanDetect;

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

    'make_finding' => [
        // Interfaces and method placeholders included in newly generated findings.
        // Supported: CanDetect::class, CanVerify::class, and CanResolve::class
        // from DataHealth\Contracts.
        'interfaces' => [CanDetect::class],
    ],

];
