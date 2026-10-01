<?php

declare(strict_types=1);

namespace DataHealth\Tests\Fixtures\DataHealth;

use Closure;
use DataHealth\Attributes\Description;
use DataHealth\Contracts\CanResolve;
use DataHealth\Contracts\CanVerify;
use DataHealth\Finding;
use DataHealth\Tests\Fixtures\Handlers\FindingResolver;
use DataHealth\Tests\Fixtures\Handlers\FindingVerifier;

#[Description('A finding that can be verified and resolved.')]
class ActionableFinding extends Finding implements CanResolve, CanVerify
{
    /** @return array<string, mixed> */
    public function buildContext(): array
    {
        return $this->context;
    }

    #[Description('Corrects the problem represented by the finding.')]
    public function resolve(): bool|callable|string
    {
        return $this->resultFor('resolve', FindingResolver::class);
    }

    #[Description('Checks whether the finding still applies.')]
    public function verify(): bool|callable|string
    {
        return $this->resultFor('verify', FindingVerifier::class);
    }

    /**
     * @param  class-string  $handler
     */
    private function resultFor(string $operation, string $handler): bool|Closure|string
    {
        [$configuredOperation, $result, $outcome] = explode(
            ':',
            (string) ($this->context['scenario'] ?? 'resolve:boolean:false'),
        );

        if ($configuredOperation !== $operation) {
            return false;
        }

        $succeeds = $outcome === 'true';

        return match ($result) {
            'boolean' => $succeeds,
            'callable' => fn (): bool => $succeeds,
            'handler' => $handler,
            default => 'invalid-handler',
        };
    }
}
