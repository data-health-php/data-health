<?php

declare(strict_types=1);

namespace DataHealth;

use DataHealth\Attributes\Async;
use DataHealth\Attributes\AutoResolve;
use DataHealth\Attributes\AutoVerify;
use DataHealth\Attributes\Description;
use DataHealth\Attributes\Key;
use DataHealth\Attributes\Urgency;
use DataHealth\Attributes\Worklist;
use DataHealth\Enums\FindingUrgency;
use DataHealth\Models\FindingRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use ReflectionClass;

abstract class Finding
{
    /** @param array<array-key, mixed> $context */
    public function __construct(
        public readonly Model $model,
        public readonly array $context = [],
    ) {}

    public static function key(): string
    {
        return self::classAttribute(Key::class)->key
            ?? str(static::class)->afterLast('\\')->toString();
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

    public static function getAsync(): ?Async
    {
        $reflection = new ReflectionClass(static::class);

        if ($reflection->getAttributes(Async::class) !== []) {
            throw new LogicException('Async may only be applied to detect(): '.static::class);
        }

        foreach ($reflection->getMethods() as $method) {
            if ($method->getName() !== 'detect' && $method->getAttributes(Async::class) !== []) {
                throw new LogicException('Async may only be applied to detect(): '.static::class.'::'.$method->getName());
            }
        }

        return self::methodAttribute('detect', Async::class);
    }

    public function isAutomaticallyResolved(): bool
    {
        return self::classAttribute(AutoResolve::class) !== null;
    }

    public function getAutoVerifyDelay(): ?int
    {
        return self::classAttribute(AutoVerify::class)?->delay;
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
