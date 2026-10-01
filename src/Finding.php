<?php

declare(strict_types=1);

namespace DataHealth;

use DataHealth\Attributes\AutoResolve;
use DataHealth\Attributes\Description;
use DataHealth\Attributes\Urgency;
use DataHealth\Attributes\Worklist;
use DataHealth\Enums\FindingUrgency;
use DataHealth\Models\FindingRecord;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

abstract class Finding
{
    /** @param array<array-key, mixed> $context */
    public function __construct(
        public readonly Model $model,
        public readonly array $context = [],
    ) {}

    /** @return array<array-key, mixed> */
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
        $finding = (new ReflectionClass(static::class))->newInstanceArgs($args);

        return app(DataHealthManager::class)->found($finding);
    }

    public static function getUrgency(): ?FindingUrgency
    {
        return self::classAttribute(Urgency::class)?->urgency;
    }

    public static function getWorklist(): ?string
    {
        return self::classAttribute(Worklist::class)?->worklist;
    }

    public static function getDescription(): ?string
    {
        return self::classAttribute(Description::class)?->description;
    }

    public static function getMethodDescription(string $method): ?string
    {
        return self::methodAttribute($method, Description::class)?->description;
    }

    public function isAutomaticallyResolved(): bool
    {
        return self::classAttribute(AutoResolve::class) !== null;
    }

    /**
     * @template TAttribute of object
     *
     * @param  class-string<TAttribute>  $attribute
     * @return TAttribute|null
     */
    private static function classAttribute(string $attribute): ?object
    {
        $reflectionAttribute = array_first(
            (new ReflectionClass(static::class))->getAttributes($attribute),
        );

        return $reflectionAttribute?->newInstance();
    }

    /**
     * @template TAttribute of object
     *
     * @param  class-string<TAttribute>  $attribute
     * @return TAttribute|null
     */
    private static function methodAttribute(string $method, string $attribute): ?object
    {
        $reflection = new ReflectionClass(static::class);

        if (! $reflection->hasMethod($method)) {
            return null;
        }

        $reflectionAttribute = array_first(
            $reflection->getMethod($method)->getAttributes($attribute),
        );

        return $reflectionAttribute?->newInstance();
    }
}
