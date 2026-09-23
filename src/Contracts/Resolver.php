<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

use DataHealth\Models\FindingRecord;

interface Resolver
{
    public function resolve(FindingRecord $record): bool;
}
