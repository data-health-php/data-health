<?php

declare(strict_types=1);

namespace DataHealth\Tests\Fixtures\Handlers;

use DataHealth\Contracts\Resolver;
use DataHealth\Models\FindingRecord;

class FindingResolver implements Resolver
{
    public function resolve(FindingRecord $record): bool
    {
        return str_ends_with((string) ($record->context['scenario'] ?? ''), ':true');
    }
}
