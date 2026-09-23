<?php

declare(strict_types=1);

namespace DataHealth\Tests\Fixtures\DataHealth;

use DataHealth\Attributes\AutoResolve;
use DataHealth\Contracts\CanResolve;
use DataHealth\Finding;

#[AutoResolve]
class AutomaticallyResolvedFinding extends Finding implements CanResolve
{
    /** @return array<string, mixed> */
    public function buildContext(): array
    {
        return $this->context;
    }

    public function resolve(): bool
    {
        return (bool) ($this->context['succeeds'] ?? false);
    }
}
