<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

interface CanDetect
{
    /**
     * @return int|callable(mixed...): bool|null
     */
    public static function detect(): int|callable|null;
}
