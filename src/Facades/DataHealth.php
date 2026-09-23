<?php

declare(strict_types=1);

namespace DataHealth\Facades;

use DataHealth\DataHealthManager;
use Illuminate\Support\Facades\Facade;

/**
 * @see \DataHealth\DataHealth
 */
class DataHealth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DataHealthManager::class;
    }
}
