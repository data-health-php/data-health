<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

interface CanVerify
{
    /**
     * @return bool|callable(mixed...): bool|string
     */
    public function verify(): bool|callable|string;
}
