<?php

declare(strict_types=1);

namespace DataHealth\Tests\Feature;

use DataHealth\Attributes\Async;
use DataHealth\Attributes\Key;
use DataHealth\Attributes\Scheduled;
use DataHealth\Contracts\CanDetect;
use DataHealth\Finding;
use DataHealth\Jobs\DetectFinding;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;

beforeEach(function () {
    $this->detectionDirectory = sys_get_temp_dir().'/data-health-detect-'.bin2hex(random_bytes(8));
    (new Filesystem)->ensureDirectoryExists($this->detectionDirectory);

    // Discovery reads file names; the fixture classes below are already loaded.
    foreach (['CommandDetectableFinding', 'CommandScheduledFinding', 'CommandPlainFinding'] as $class) {
        file_put_contents($this->detectionDirectory.'/'.$class.'.php', '<?php');
    }

    app()->setBasePath($this->detectionDirectory);
    config()->set('data-health.directories', ['.' => __NAMESPACE__]);
    config()->set('queue.default', 'sync');
    CommandDetectableFinding::$runs = 0;
    CommandScheduledFinding::$runs = 0;
    CommandDetectableFinding::$fail = false;
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->detectionDirectory);
});

it('runs every detectable finding synchronously regardless of schedule and async attributes', function () {
    config()->set('data-health.scheduler.enabled', false);
    Queue::fake();

    $this->artisan('health:detect')
        ->expectsOutputToContain('2 completed, 0 skipped, 0 failed')
        ->assertSuccessful();

    expect(CommandDetectableFinding::$runs)->toBe(1)
        ->and(CommandScheduledFinding::$runs)->toBe(1);
    Queue::assertNothingPushed();
});

it('selects findings by key or class and runs each once', function () {
    $this->artisan('health:detect', ['findings' => ['command-detectable', CommandDetectableFinding::class]])
        ->expectsOutputToContain('1 completed, 0 skipped, 0 failed')
        ->assertSuccessful();

    expect(CommandDetectableFinding::$runs)->toBe(1)
        ->and(CommandScheduledFinding::$runs)->toBe(0);
});

it('excludes findings by key or class', function (string $excluded) {
    $this->artisan('health:detect', ['--except' => [$excluded]])->assertSuccessful();

    expect(CommandDetectableFinding::$runs)->toBe(0)
        ->and(CommandScheduledFinding::$runs)->toBe(1);
})->with(['command-detectable', CommandDetectableFinding::class]);

it('applies exclusions to explicit selections', function () {
    $this->artisan('health:detect', [
        'findings' => ['command-detectable', CommandScheduledFinding::class],
        '--except' => ['command-detectable', CommandScheduledFinding::class],
    ])->expectsOutputToContain('No detectable findings selected.')->assertSuccessful();

    expect(CommandDetectableFinding::$runs)->toBe(0)
        ->and(CommandScheduledFinding::$runs)->toBe(0);
});

it('validates all selectors before detecting or dispatching anything', function (string $option, string $selector, bool $async) {
    Queue::fake();

    $this->artisan('health:detect', [
        'findings' => ['command-detectable'],
        $option => ['command-detectable', $selector],
        '--async' => $async,
    ])->expectsOutputToContain($selector)->assertFailed();

    expect(CommandDetectableFinding::$runs)->toBe(0);
    Queue::assertNothingPushed();
})->with(['findings', '--except'])
    ->with(['MissingFinding', CommandPlainFinding::class])
    ->with([false, true]);

it('requires async mode for queue routing', function (string $option) {
    $this->artisan('health:detect', [$option => 'data-health'])
        ->expectsOutputToContain('--queue and --connection require --async.')
        ->assertFailed();

    expect(CommandDetectableFinding::$runs)->toBe(0);
})->with(['--queue', '--connection']);

it('rejects empty queue routing values', function (string $option) {
    $this->artisan('health:detect', ['--async' => true, $option => ''])
        ->expectsOutputToContain($option.' requires a non-empty string value.')
        ->assertFailed();
})->with(['--queue', '--connection']);

it('succeeds when no findings are discovered', function () {
    config()->set('data-health.directories', []);

    $this->artisan('health:detect')
        ->expectsOutputToContain('No detectable findings selected.')
        ->assertSuccessful();
});

it('continues after a detection failure and returns a failing exit code', function () {
    CommandDetectableFinding::$fail = true;

    $this->artisan('health:detect')
        ->expectsOutputToContain('command-detectable: Detection failed.')
        ->expectsOutputToContain('1 completed, 0 skipped, 1 failed')
        ->assertFailed();

    expect(CommandScheduledFinding::$runs)->toBe(1);
});

it('queues findings with attribute routing or application defaults', function () {
    Queue::fake();

    $this->artisan('health:detect', ['--async' => true])
        ->expectsOutputToContain('2 queued, 0 skipped, 0 failed')
        ->assertSuccessful();

    Queue::assertPushed(DetectFinding::class, 2);
    Queue::assertPushed(DetectFinding::class, fn (DetectFinding $job) => $job->finding === CommandDetectableFinding::class
        && $job->queue === null && $job->connection === null);
    Queue::assertPushed(DetectFinding::class, fn (DetectFinding $job) => $job->finding === CommandScheduledFinding::class
        && $job->queue === 'health' && $job->connection === 'redis');
    expect(CommandDetectableFinding::$runs)->toBe(0)
        ->and(CommandScheduledFinding::$runs)->toBe(0);
});

it('overrides queue routing independently', function (array $options, string $queue, string $connection) {
    Queue::fake();

    $this->artisan('health:detect', [
        'findings' => [CommandScheduledFinding::class],
        '--async' => true,
        ...$options,
    ])->assertSuccessful();

    Queue::assertPushed(DetectFinding::class, fn (DetectFinding $job) => $job->queue === $queue && $job->connection === $connection);
})->with([
    'queue' => [['--queue' => 'manual'], 'manual', 'redis'],
    'connection' => [['--connection' => 'database'], 'health', 'database'],
    'both' => [['--queue' => 'manual', '--connection' => 'database'], 'manual', 'database'],
]);

it('skips detections that are already queued or running', function () {
    Queue::fake();

    $this->artisan('health:detect', ['--async' => true])->assertSuccessful();
    $this->artisan('health:detect', ['--async' => true])
        ->expectsOutputToContain('0 queued, 2 skipped, 0 failed')
        ->assertSuccessful();

    Queue::assertPushed(DetectFinding::class, 2);
});

it('continues after dispatch failures and releases the failed dispatch lock', function () {
    Queue::fake();
    $dispatcher = app(Dispatcher::class);
    $failingDispatcher = Mockery::mock(Dispatcher::class);
    $failingDispatcher->shouldReceive('dispatch')->andReturnUsing(function (DetectFinding $job) use ($dispatcher): mixed {
        if ($job->connection === null) {
            throw new RuntimeException('Queue unavailable.');
        }

        return $dispatcher->dispatch($job);
    });
    app()->instance(Dispatcher::class, $failingDispatcher);

    $this->artisan('health:detect', ['--async' => true])
        ->expectsOutputToContain('command-detectable: Queue unavailable.')
        ->expectsOutputToContain('1 queued, 0 skipped, 1 failed')
        ->assertFailed();

    $this->artisan('health:detect', ['--async' => true, '--connection' => 'database'])
        ->expectsOutputToContain('1 queued, 1 skipped, 0 failed')
        ->assertSuccessful();
});

it('executes callable detection results in queued jobs', function () {
    (new DetectFinding(CommandScheduledFinding::class))->handle();

    expect(CommandScheduledFinding::$runs)->toBe(1);
});

#[Key('command-detectable')]
final class CommandDetectableFinding extends Finding implements CanDetect
{
    public static int $runs = 0;

    public static bool $fail = false;

    public static function detect(): ?int
    {
        self::$runs++;

        if (self::$fail) {
            throw new RuntimeException('Detection failed.');
        }

        return null;
    }
}

#[Async(queue: 'health', connection: 'redis')]
#[Scheduled('0 0 1 1 *')]
final class CommandScheduledFinding extends Finding implements CanDetect
{
    public static int $runs = 0;

    public static function detect(): callable
    {
        return function (Filesystem $filesystem): int {
            expect($filesystem)->toBeInstanceOf(Filesystem::class);
            self::$runs++;

            return 3;
        };
    }
}

final class CommandPlainFinding extends Finding {}
