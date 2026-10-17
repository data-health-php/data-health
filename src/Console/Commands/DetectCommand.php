<?php

declare(strict_types=1);

namespace DataHealth\Console\Commands;

use DataHealth\Attributes\Async;
use DataHealth\Contracts\CanDetect;
use DataHealth\Finding;
use DataHealth\FindingRegistry;
use DataHealth\Jobs\DetectFinding;
use Illuminate\Bus\UniqueLock;
use Illuminate\Console\Command;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository as Cache;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;

class DetectCommand extends Command
{
    /** @var string */
    protected $signature = 'health:detect
        {findings?* : Finding keys or fully qualified class names (defaults to all detectable findings)}
        {--except=* : Exclude a finding key or fully qualified class name}
        {--async : Queue detection instead of running it synchronously}
        {--queue= : Override the detection queue (requires --async)}
        {--connection= : Override the queue connection (requires --async)}';

    /** @var string */
    protected $description = 'Run detection for all or selected Data Health findings';

    public function handle(FindingRegistry $registry): int
    {
        $async = (bool) $this->option('async');

        try {
            $queue = $this->stringOption('queue');
            $connection = $this->stringOption('connection');

            if (! $async && ($queue !== null || $connection !== null)) {
                throw new InvalidArgumentException('--queue and --connection require --async.');
            }

            /** @var list<string> $selectors */
            $selectors = $this->argument('findings');
            /** @var list<string> $exclusions */
            $exclusions = $this->option('except');

            $findings = $selectors === [] ? $registry->detectable()->all() : $this->selectFindings($registry, $selectors);
            $findings = array_diff_key($findings, $this->selectFindings($registry, $exclusions));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($findings === []) {
            $this->info('No detectable findings selected.');

            return self::SUCCESS;
        }

        $completed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($findings as $key => $finding) {
            try {
                if ($async) {
                    if (! $this->queueFinding($finding, $queue, $connection)) {
                        $skipped++;
                        $this->line("Skipped (already queued or running): {$key}");

                        continue;
                    }
                } else {
                    (new DetectFinding($finding))->handle();
                }

                $completed++;
                $this->info(($async ? 'Queued: ' : 'Completed: ').$key);
            } catch (Throwable $exception) {
                $failed++;
                $this->error($key.': '.$exception->getMessage());
            }
        }

        $action = $async ? 'queued' : 'completed';
        $this->line("Detection summary: {$completed} {$action}, {$skipped} skipped, {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<string>  $selectors
     * @return array<string, class-string<Finding&CanDetect>>
     */
    private function selectFindings(FindingRegistry $registry, array $selectors): array
    {
        $findings = [];

        foreach ($selectors as $selector) {
            [$key, $finding] = $registry->getKeyAndClass($selector);

            if (! is_a($finding, CanDetect::class, true)) {
                throw new InvalidArgumentException($selector.' does not implement '.CanDetect::class.'.');
            }

            $findings[$key] = $finding;
        }

        return $findings;
    }

    /** @param class-string<Finding&CanDetect> $finding */
    private function queueFinding(string $finding, ?string $queue, ?string $connection): bool
    {
        $attribute = (new ReflectionClass($finding))->getAttributes(Async::class)[0] ?? null;
        $async = $attribute?->newInstance();

        $job = (new DetectFinding($finding))
            ->onQueue($queue ?? $async?->queue)
            ->onConnection($connection ?? $async?->connection);

        $lock = new UniqueLock(app(Cache::class));

        if (! $lock->acquire($job)) {
            return false;
        }

        try {
            app(Dispatcher::class)->dispatch($job);
        } catch (Throwable $exception) {
            $lock->release($job);

            throw $exception;
        }

        return true;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        if ($value !== null && (! is_string($value) || trim($value) === '')) {
            throw new InvalidArgumentException('--'.$name.' requires a non-empty string value.');
        }

        return $value;
    }
}
