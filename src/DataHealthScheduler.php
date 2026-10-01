<?php

declare(strict_types=1);

namespace DataHealth;

use Cron\CronExpression;
use DataHealth\Attributes\Async;
use DataHealth\Attributes\Scheduled;
use DataHealth\Contracts\CanDetect;
use DataHealth\Jobs\DetectFinding;
use Illuminate\Console\Scheduling\Schedule;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;

class DataHealthScheduler
{
    public function __construct(
        private readonly FindingRegistry $registry,
        private readonly Schedule $schedule,
    ) {}

    public function schedule(): void
    {
        if (config('data-health.scheduler.enabled', false) !== true) {
            return;
        }

        foreach ($this->registry->schedulable() as $finding) {
            $this->scheduleFinding($finding);
        }
    }

    /** @param class-string<Finding> $finding */
    private function scheduleFinding(string $finding): void
    {
        if (! is_a($finding, CanDetect::class, true)) {
            throw new LogicException($finding.' must implement '.CanDetect::class.' to be scheduled');
        }

        $expression = $this->cronExpression($finding);
        $async = $this->async($finding);

        $event = $async === null
            ? $this->schedule->call($finding::detect(...))
            : $this->schedule->job(
                new DetectFinding($finding),
                $async->queue,
                $async->connection,
            );

        $event
            ->name('data-health:detect:'.$finding)
            ->cron($expression)
            ->withoutOverlapping()
            ->onOneServer();
    }

    /** @param class-string<Finding> $finding */
    private function async(string $finding): ?Async
    {
        $attribute = (new ReflectionClass($finding))
            ->getAttributes(Async::class)[0] ?? null;

        return $attribute?->newInstance();
    }

    /** @param class-string<Finding> $finding */
    private function cronExpression(string $finding): string
    {
        $attribute = (new ReflectionClass($finding))
            ->getAttributes(Scheduled::class)[0] ?? null;

        if ($attribute === null) {
            throw new LogicException($finding.' is missing the '.Scheduled::class.' attribute');
        }

        $expression = $attribute->newInstance()->expression;

        if (! CronExpression::isValidExpression($expression)) {
            throw new InvalidArgumentException("Invalid cron expression for {$finding}: {$expression}");
        }

        return $expression;
    }
}
