<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

interface Detector
{
    /**
     * Search candidate records and report each occurrence with the finding's found() method.
     * Leave business-data corrections to resolution; AutoResolve may run for new records.
     * Return null by default; core execution ignores optional integer counts.
     */
    public function detect(): ?int;
}
