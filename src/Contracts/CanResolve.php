<?php

declare(strict_types=1);

namespace DataHealth\Contracts;

interface CanResolve
{
    /**
     * Recheck one occurrence and apply a known correction using application logic.
     * Use the attached model and identifying context; preserve domain rules and safe retries.
     * Return true when the problem is corrected or already gone; the manager resolves the record.
     * Return false when correction cannot complete safely, and throw for invalid input or failures.
     * Implementing resolution exposes an action; AutoResolve separately opts into execution.
     * Callables and Resolver class names delegate the same correction through Laravel's container.
     *
     * @return bool|callable(mixed...): bool|class-string<Resolver>
     */
    public function resolve(): bool|callable|string;
}
