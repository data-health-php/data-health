<?php

declare(strict_types=1);

namespace DataHealth;

use DataHealth\Attributes\Scheduled;
use DataHealth\Contracts\CanDetect;
use Illuminate\Support\Collection;
use ReflectionClass;
use RuntimeException;

class FindingRegistry
{
    /** @var Collection<string, class-string<Finding>> */
    private Collection $fails;

    /** @return Collection<string, class-string<Finding>> */
    public function all(): Collection
    {
        $this->bootIfNotBooted();

        return $this->fails;
    }

    /** @return Collection<string, class-string<Finding&CanDetect>> */
    public function detectable(): Collection
    {
        $this->bootIfNotBooted();

        return $this->fails->filter(fn (string $class) => is_a($class, CanDetect::class, true));
    }

    /** @return Collection<string, class-string<Finding&CanDetect>> */
    public function schedulable(): Collection
    {
        $this->bootIfNotBooted();

        return $this->detectable()->filter(
            fn (string $class) => ! empty(new ReflectionClass($class)->getAttributes(Scheduled::class)),
        );
    }

    public function getKey(string $keyOrClass): string
    {
        $this->bootIfNotBooted();

        [$key] = $this->getKeyAndClass($keyOrClass);

        return $key;
    }

    /** @return class-string<Finding> */
    public function getClass(string $keyOrClass): string
    {
        $this->bootIfNotBooted();

        [, $class] = $this->getKeyAndClass($keyOrClass);

        return $class;
    }

    /** @return array{string, class-string<Finding>} */
    public function getKeyAndClass(string $keyOrClass): array
    {
        $this->bootIfNotBooted();

        foreach ($this->fails as $key => $class) {
            if ($class === $keyOrClass) {
                return [$key, $class];
            }
        }

        if ($this->fails->has($keyOrClass)) {
            $class = $this->fails->get($keyOrClass);

            if ($class !== null) {
                return [$keyOrClass, $class];
            }
        }

        throw new RuntimeException("Fail with key or class {$keyOrClass} not found");
    }

    private function bootIfNotBooted(): void
    {
        if (! isset($this->fails)) {
            $this->boot();
        }
    }

    private function boot(): void
    {
        $this->fails = collect();

        foreach (config('data-health.directories') as $dir => $namespace) {
            $files = glob(base_path($dir.'/*.php'));

            if ($files === false) {
                throw new RuntimeException('Failed to read directory: '.$dir);
            }

            /** @var Collection<string, class-string<Finding>> $failsInDir */
            $failsInDir = collect($files)
                ->map(fn (string $path) => $namespace.pathinfo($path, PATHINFO_FILENAME))
                ->mapWithKeys(fn (string $class) => [str($class)->afterLast('\\')->toString() => $class]);

            $this->fails = $this->fails->merge($failsInDir);
        }
    }
}
