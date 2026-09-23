<?php

declare(strict_types=1);

namespace DataHealth\Facades;

use DataHealth\DataHealthCheckCursor;
use Illuminate\Support\Facades\Facade;

class CheckCursor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DataHealthCheckCursor::class;
    }
}
