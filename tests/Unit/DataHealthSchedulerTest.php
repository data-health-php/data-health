<?php

declare(strict_types=1);

use DataHealth\Attributes\Async;
use DataHealth\Attributes\Scheduled;
use DataHealth\Contracts\CanDetect;
use DataHealth\DataHealthScheduler;
use DataHealth\Finding;
use DataHealth\FindingRegistry;
use DataHealth\Jobs\DetectFinding;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    ScheduledFinding::$detected = false;
    AsynchronousScheduledFinding::$detected = false;
});

it('does not register detections when scheduling is disabled', function () {
    config()->set('data-health.scheduler.enabled', false);

    $schedule = app(Schedule::class);
    $eventCount = count($schedule->events());

    (new DataHealthScheduler(
        new SchedulerFindingRegistry([ScheduledFinding::class]),
        $schedule,
    ))->schedule();

    expect($schedule->events())->toHaveCount($eventCount);
});

it('schedules detectable findings using their cron expression', function () {
    $schedule = app(Schedule::class);
    $eventCount = count($schedule->events());

    (new DataHealthScheduler(
        new SchedulerFindingRegistry([ScheduledFinding::class]),
        $schedule,
    ))->schedule();

    $events = $schedule->events();
    $event = $events[$eventCount];

    expect($event->expression)->toBe('15 * * * *')
        ->and($event->description)->toBe('data-health:detect:'.ScheduledFinding::class)
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();

    $event->run($this->app);

    expect(ScheduledFinding::$detected)->toBeTrue();
});

it('queues asynchronous scheduled detections once while pending', function () {
    Queue::fake();

    $schedule = app(Schedule::class);
    $eventCount = count($schedule->events());

    (new DataHealthScheduler(
        new SchedulerFindingRegistry([AsynchronousScheduledFinding::class]),
        $schedule,
    ))->schedule();

    $event = $schedule->events()[$eventCount];

    $event->run($this->app);
    $event->run($this->app);

    Queue::assertPushed(DetectFinding::class, 1);
    Queue::assertPushed(DetectFinding::class, function (DetectFinding $job) {
        return $job->finding === AsynchronousScheduledFinding::class
            && $job->queue === 'data-health'
            && $job->connection === 'redis';
    });

    expect(AsynchronousScheduledFinding::$detected)->toBeFalse();
});

it('runs an asynchronous detection from its unique queued job', function () {
    $job = new DetectFinding(AsynchronousScheduledFinding::class);

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe(AsynchronousScheduledFinding::class);

    $job->handle();

    expect(AsynchronousScheduledFinding::$detected)->toBeTrue();
});

it('rejects invalid cron expressions before registering an event', function () {
    $schedule = app(Schedule::class);
    $eventCount = count($schedule->events());
    $scheduler = new DataHealthScheduler(
        new SchedulerFindingRegistry([InvalidScheduleFinding::class]),
        $schedule,
    );

    expect(fn () => $scheduler->schedule())
        ->toThrow(
            InvalidArgumentException::class,
            'Invalid cron expression for '.InvalidScheduleFinding::class.': not a cron expression',
        );

    expect($schedule->events())->toHaveCount($eventCount);
});

it('reports when a schedulable finding has no scheduled attribute', function () {
    $scheduler = new DataHealthScheduler(
        new SchedulerFindingRegistry([UnscheduledFinding::class]),
        app(Schedule::class),
    );

    expect(fn () => $scheduler->schedule())
        ->toThrow(
            LogicException::class,
            UnscheduledFinding::class.' is missing the '.Scheduled::class.' attribute',
        );
});

it('reports when a schedulable finding cannot detect', function () {
    $scheduler = new DataHealthScheduler(
        new SchedulerFindingRegistry([NonDetectableFinding::class]),
        app(Schedule::class),
    );

    expect(fn () => $scheduler->schedule())
        ->toThrow(
            LogicException::class,
            NonDetectableFinding::class.' must implement '.CanDetect::class.' to be scheduled',
        );
});

/** @param list<class-string<Finding>> $findings */
final class SchedulerFindingRegistry extends FindingRegistry
{
    public function __construct(private readonly array $findings) {}

    /** @return Collection<int, class-string<Finding>> */
    public function schedulable(): Collection
    {
        return collect($this->findings);
    }
}

#[Scheduled('15 * * * *')]
final class ScheduledFinding extends Finding implements CanDetect
{
    public static bool $detected = false;

    public static function detect(): int
    {
        self::$detected = true;

        return 0;
    }
}

#[Scheduled('not a cron expression')]
final class InvalidScheduleFinding extends Finding implements CanDetect
{
    public static function detect(): int
    {
        return 0;
    }
}

#[Async(queue: 'data-health', connection: 'redis')]
#[Scheduled('30 * * * *')]
final class AsynchronousScheduledFinding extends Finding implements CanDetect
{
    public static bool $detected = false;

    public static function detect(): int
    {
        self::$detected = true;

        return 0;
    }
}

final class UnscheduledFinding extends Finding implements CanDetect
{
    public static function detect(): int
    {
        return 0;
    }
}

#[Scheduled('15 * * * *')]
final class NonDetectableFinding extends Finding {}
