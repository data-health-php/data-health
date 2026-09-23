<?php

declare(strict_types=1);

namespace DataHealth\Tests\Fixtures\DataHealth;

use DataHealth\Attributes\Scheduled;
use DataHealth\Contracts\CanDetect;
use DataHealth\Finding;

#[Scheduled('0 * * * *')]
class ScheduledFinding extends Finding implements CanDetect
{
    public static function detect(): int
    {
        return 0;
    }
}
