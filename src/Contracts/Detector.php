<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

interface Detector
{
    public function detect(): ?int;
}
