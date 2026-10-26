<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

use DataHealth\Models\FindingRecord;

interface Verifier
{
    /**
     * Recheck this record's model and context without business-data changes or detection.
     * Related queries and shared read-only predicates may support this occurrence's check.
     * Return true when it no longer applies, false when it does, and throw for invalid input
     * or an unreliable check. The manager marks the record resolved only on true.
     */
    public function verify(FindingRecord $record): bool;
}
