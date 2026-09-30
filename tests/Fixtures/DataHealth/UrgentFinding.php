<?php

declare(strict_types=1);

namespace DataHealth\Tests\Fixtures\DataHealth;

use DataHealth\Attributes\Urgency;
use DataHealth\Attributes\Worklist;
use DataHealth\Enums\FindingUrgency;
use DataHealth\Finding;

#[Urgency(FindingUrgency::SOON)]
#[Worklist('data-quality')]
class UrgentFinding extends Finding
{
    /** @return array<string, mixed> */
    public function buildContext(): array
    {
        return $this->context;
    }
}
