<?php

declare(strict_types=1);

namespace DataHealth;

use DataHealth\Attributes\AutoResolve;
use DataHealth\Attributes\Urgency;
use DataHealth\Enums\FindingUrgency;
use DataHealth\Models\FindingRecord;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

abstract class Finding
{
    public function __construct(
        public readonly Model $model,
        public readonly array $context = [],
    ) {}

    public function buildContext(): array
    {
        return [];
    }

    public function key(): string
    {
        return str(static::class)->afterLast('\\')->toString();
    }

    public static function found(mixed ...$args): FindingRecord
    {
        return app(DataHealthManager::class)->found(new static(...$args));
    }

    public static function getUrgency(): ?FindingUrgency
    {
        $reflection = new ReflectionClass(static::class);

        $attributes = $reflection->getAttributes(Urgency::class);

        $attribute = array_first($attributes);

        if ($attribute === null) {
            return null;
        }

        return array_first($attribute->getArguments()) ?? null;
    }

    public function isAutomaticallyResolved(): bool
    {
        $reflection = new ReflectionClass(static::class);

        $attributes = $reflection->getAttributes(AutoResolve::class);

        return !empty($attributes);
    }
}
