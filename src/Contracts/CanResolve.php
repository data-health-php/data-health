<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

interface CanResolve
{
    /**
     * @return bool|callable(mixed...): bool|string
     */
    public function resolve(): bool|callable|string;
}
