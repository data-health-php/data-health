<?php

declare(strict_types=1);

namespace DataHealth\Attributes;

use Attribute;
use DataHealth\Enums\FindingUrgency;

#[Attribute(Attribute::TARGET_CLASS)]
class Urgency
{
    public function __construct(public readonly FindingUrgency $urgency) {}
}
