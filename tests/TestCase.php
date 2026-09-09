<?php

declare(strict_types=1);

namespace DataHealth\DataHealth\Tests;

use DataHealth\DataHealth\DataHealthServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DataHealthServiceProvider::class,
        ];
    }
}
