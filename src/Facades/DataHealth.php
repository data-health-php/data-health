<?php

declare(strict_types=1);

namespace DataHealth\DataHealth\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \DataHealth\DataHealth\DataHealth
 */
class DataHealth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \DataHealth\DataHealth\DataHealth::class;
    }
}
