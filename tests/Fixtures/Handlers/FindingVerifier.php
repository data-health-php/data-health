<?php

declare(strict_types=1);

namespace DataHealth\Tests\Fixtures\Handlers;

use DataHealth\Contracts\Verifier;
use DataHealth\Models\FindingRecord;

class FindingVerifier implements Verifier
{
    public function verify(FindingRecord $record): bool
    {
        return str_ends_with((string) ($record->context['scenario'] ?? ''), ':true');
    }
}
