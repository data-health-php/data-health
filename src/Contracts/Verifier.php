<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

use DataHealth\Models\FindingRecord;

interface Verifier
{
    public function verify(FindingRecord $record): bool;
}
