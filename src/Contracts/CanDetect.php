<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

interface CanDetect
{
    /**
     * Return a Detector class name to delegate detection to a dedicated class.
     *
     * @return int|(callable(mixed...): (int|null))|string|null
     */
    public static function detect(): int|callable|string|null;
}
