<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

interface CanVerify
{
    /**
     * @return bool|callable(mixed...): bool|class-string<Verifier>
     */
    public function verify(): bool|callable|string;
}
