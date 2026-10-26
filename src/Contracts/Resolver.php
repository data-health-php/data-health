<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

use DataHealth\Models\FindingRecord;

interface Resolver
{
    /**
     * Recheck this record's occurrence and apply a known correction through application logic.
     * Preserve domain rules and safe retries; return true when corrected or already gone.
     * Return false when correction cannot complete safely, and throw for invalid input or failures.
     * The manager resolves the record on true; automatic execution requires AutoResolve.
     */
    public function resolve(FindingRecord $record): bool;
}
