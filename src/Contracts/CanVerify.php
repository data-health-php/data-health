<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

interface CanVerify
{
    /**
     * Recheck one occurrence using its attached model, context, and relevant related data.
     * Do not change business data or rerun detection. Shared read-only predicates are allowed.
     * Return true when the problem no longer applies; the manager marks the record resolved.
     * Return false when it still applies, and throw when input is invalid or the check fails.
     * Callables and Verifier class names delegate the same check through Laravel's container.
     *
     * @return bool|callable(mixed...): bool|class-string<Verifier>
     */
    public function verify(): bool|callable|string;
}
