<?php

declare(strict_types=1);

namespace DataHealth\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Scheduled
{
    public function __construct(public readonly string $expression) {}
}
