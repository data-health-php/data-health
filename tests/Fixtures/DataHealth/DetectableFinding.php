<?php

declare(strict_types=1);

namespace DataHealth\Tests\Fixtures\DataHealth;

use DataHealth\Attributes\Description;
use DataHealth\Contracts\CanDetect;
use DataHealth\Finding;

class DetectableFinding extends Finding implements CanDetect
{
    #[Description('Searches for detectable problems.')]
    public static function detect(): int
    {
        return 0;
    }
}
