<?php

declare(strict_types=1);

namespace DataHealth\Attributes;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS)]
class AutoVerify
{
    public function __construct(public readonly int $delay = 0)
    {
        if ($delay < 0) {
            throw new InvalidArgumentException('The automatic verification delay must be zero or greater.');
        }
    }
}
