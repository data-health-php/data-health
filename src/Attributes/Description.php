<?php

declare(strict_types=1);

namespace DataHealth\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Description
{
    public function __construct(public readonly string $description) {}
}
