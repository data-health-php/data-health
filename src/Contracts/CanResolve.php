<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

interface CanResolve
{
    /**
     * @return bool|callable(mixed...): bool|class-string<Resolver>
     */
    public function resolve(): bool|callable|string;
}
