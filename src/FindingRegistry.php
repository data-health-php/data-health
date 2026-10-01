<?php

declare(strict_types=1);

namespace DataHealth;

use DataHealth\Attributes\Scheduled;
use DataHealth\Contracts\CanDetect;
use FilesystemIterator;
use Illuminate\Support\Collection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use SplFileInfo;

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
            $this->fails = $this->fails->merge($this->discover($dir, $namespace));
        }
    }

    /** @return Collection<string, class-string<Finding>> */
    private function discover(string $configuredDirectory, string $namespace): Collection
    {
        $directory = realpath(base_path($configuredDirectory));

        if ($directory === false || ! is_dir($directory)) {
            /** @var Collection<string, class-string<Finding>> $findings */
            $findings = collect();

            return $findings;
        }

        if (! is_readable($directory)) {
            throw new RuntimeException('Finding directory is not readable: '.$configuredDirectory);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        /** @var Collection<int, SplFileInfo> $files */
        $files = collect(iterator_to_array($iterator, false));

        /** @var Collection<string, class-string<Finding>> $findings */
        $findings = $files
            ->filter(fn (SplFileInfo $file) => $file->isFile() && $file->getExtension() === 'php')
            ->sortBy(fn (SplFileInfo $file) => $file->getPathname(), SORT_STRING)
            ->map(function (SplFileInfo $file) use ($directory, $namespace): string {
                $relativePath = substr($file->getPathname(), strlen($directory) + 1, -4);
                $relativeClass = str_replace(['/', '\\'], '\\', $relativePath);

                return rtrim($namespace, '\\').'\\'.$relativeClass;
            })
            ->filter(function (string $class): bool {
                if (! class_exists($class) || ! is_subclass_of($class, Finding::class)) {
                    return false;
                }

                return ! (new ReflectionClass($class))->isAbstract();
            })
            ->mapWithKeys(fn (string $class) => [$class::key() => $class]);

        return $findings;
    }
}
