<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

interface CanDetect
{
    /**
     * Search candidate records and report each occurrence with self::found().
     * Return null for inline detection; leave business-data corrections to resolution.
     * Reporting a new occurrence may invoke resolution when AutoResolve is enabled.
     *
     * Callables and Detector class names delegate detection through Laravel's container.
     * Integer counts are optional and ignored by core detection execution.
     *
     * @return int|(callable(mixed...): (int|null))|class-string<Detector>|null
     */
    public static function detect(): int|callable|string|null;
}
