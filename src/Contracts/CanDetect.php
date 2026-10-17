<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

interface CanDetect
{
    /**
     * @return int|(callable(mixed...): (int|null))|null
     */
    public static function detect(): int|callable|null;
}
