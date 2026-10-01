<?php

declare(strict_types=1);

namespace DataHealth\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Async
{
    public function __construct(
        public readonly ?string $queue = null,
        public readonly ?string $connection = null,
    ) {}
}
