<?php

declare(strict_types=1);

namespace DataHealth\Tests\Fixtures\DataHealth;

use DataHealth\Contracts\CanDetect;
use DataHealth\Finding;

class DetectableFinding extends Finding implements CanDetect
{
    public static function detect(): int
    {
        return 0;
    }
}
