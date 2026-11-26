<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

interface CanExplain
{
    /**
     * Read current, explicitly selected facts for this occurrence.
     * Do not change business data or finding status, or invoke verification/resolution.
     * Refresh relevant data and return JSON-serializable values safe for the intended audience.
     * still_applies describes this observation; it does not update the stored record status.
     * observed_at is the ISO 8601 time of this observation, not the detection time.
     * Throw when facts cannot be read; an unavailable explanation is not a cleared problem.
     *
     * @return array{expected: string, actual: array<string, mixed>, still_applies: bool, observed_at: string}
     */
    public function explain(): array;
}
