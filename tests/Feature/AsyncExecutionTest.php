<?php

declare(strict_types=1);

namespace DataHealth\Tests\Feature\Async;

use DataHealth\Attributes\Async;
use DataHealth\Attributes\AutoResolve;
use DataHealth\Attributes\AutoVerify;
use DataHealth\Attributes\Scheduled;
use DataHealth\Contracts\CanDetect;
use DataHealth\DataHealthManager;
use DataHealth\DataHealthScheduler;
use DataHealth\Enums\RecordStatus;
use DataHealth\FindingRegistry;
use DataHealth\Jobs\DetectFinding;
use DataHealth\Jobs\VerifyFinding;
use DataHealth\Tests\Fixtures\DataHealth\ActionableFinding;
use DataHealth\Tests\Fixtures\Models\TestModel;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Queue;
use LogicException;

beforeEach(function () {
    $this->migrateDataHealthDatabase();
    $this->asyncDirectory = sys_get_temp_dir().'/data-health-async-'.bin2hex(random_bytes(8));
    (new Filesystem)->ensureDirectoryExists($this->asyncDirectory);

    foreach (['AsyncDetectableFinding', 'SynchronousFinding', 'AutomaticFinding'] as $class) {
        file_put_contents($this->asyncDirectory.'/'.$class.'.php', '<?php');
    }

    app()->setBasePath($this->asyncDirectory);
    config()->set('data-health.directories', ['.' => __NAMESPACE__]);
    $this->manager = app(DataHealthManager::class);
    $this->model = TestModel::create(['name' => 'Example']);
    AsyncDetectableFinding::$runs = 0;
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->asyncDirectory);
});

it('queues attributed detection without a command flag', function () {
    Queue::fake();
    $this->artisan('health:detect', ['findings' => [AsyncDetectableFinding::class]])
        ->expectsOutputToContain('1 queued, 0 skipped, 0 failed')
        ->assertSuccessful();

    Queue::assertPushed(DetectFinding::class, fn (DetectFinding $job): bool => $job->finding === AsyncDetectableFinding::class
        && $job->queue === 'detection' && $job->connection === 'redis');
    expect(AsyncDetectableFinding::$runs)->toBe(0);
});

it('honors detection method attributes in scheduled detection', function () {
    Queue::fake();
    $schedule = app(Schedule::class);
    $count = count($schedule->events());
    (new DataHealthScheduler(new FindingRegistry, $schedule))->schedule();
    $schedule->events()[$count]->run(app());

    Queue::assertPushed(DetectFinding::class, fn (DetectFinding $job): bool => $job->finding === AsyncDetectableFinding::class
        && $job->queue === 'detection' && $job->connection === 'redis');
});

it('returns immediate boolean action results on a finding with async detection', function (string $operation, string $result, bool $succeeds) {
    Queue::fake();
    $record = $this->manager->found(new AsyncDetectableFinding($this->model, [
        'scenario' => $operation.':'.$result.':'.($succeeds ? 'true' : 'false'),
    ]));

    expect($this->manager->{$operation}($record))->toBe($succeeds)
        ->and($record->fresh()->status)->toBe($succeeds ? RecordStatus::Resolved : RecordStatus::Active);
    Queue::assertNothingPushed();
})->with(['verify', 'resolve'])->with(['boolean', 'callable', 'handler'])->with([true, false]);

it('reports completed verification and resolution from commands immediately', function (string $operation, bool $succeeds) {
    Queue::fake();
    $record = $this->manager->found(new AsyncDetectableFinding($this->model, [
        'scenario' => $operation.':boolean:'.($succeeds ? 'true' : 'false'),
    ]));
    $action = $operation === 'verify' ? 'verified' : 'resolved';
    $this->artisan('health:'.$operation, ['id' => $record->id])
        ->expectsOutput($succeeds
            ? "Finding record {$record->id} {$action}."
            : "Finding record {$record->id} could not be {$action}.")
        ->assertExitCode($succeeds ? 0 : 1);
    Queue::assertNothingPushed();
})->with(['verify', 'resolve'])->with([true, false]);

it('can force synchronous detection', function () {
    Queue::fake();
    $this->artisan('health:detect', ['findings' => [AsyncDetectableFinding::class], '--sync' => true])->assertSuccessful();
    expect(AsyncDetectableFinding::$runs)->toBe(1);
    Queue::assertNothingPushed();
});

it('rejects conflicting detection execution flags', function () {
    Queue::fake();
    $this->artisan('health:detect', ['--sync' => true, '--async' => true])
        ->expectsOutput('--async and --sync cannot be combined.')
        ->assertFailed();
    Queue::assertNothingPushed();
});

it('keeps automatic resolution immediate and automatic verification on application defaults', function () {
    $this->freezeSecond();
    Queue::fake();
    $record = $this->manager->found(new AutomaticFinding($this->model, ['scenario' => 'resolve:boolean:true']));

    expect($record->fresh()->status)->toBe(RecordStatus::Resolved);
    Queue::assertPushed(VerifyFinding::class, fn (VerifyFinding $job): bool => $job->recordId === $record->id
        && $job->queue === null && $job->connection === null
        && $job->delay?->equalTo(now()->addMinutes(2)) === true && $job->afterCommit === true);
    Queue::assertPushed(VerifyFinding::class, 1);
});

it('reports mixed synchronous and queued detection counts', function () {
    Queue::fake();
    $this->artisan('health:detect', ['findings' => [AsyncDetectableFinding::class, SynchronousFinding::class]])
        ->expectsOutputToContain('1 completed, 1 queued, 0 skipped, 0 failed')
        ->assertSuccessful();
});

it('reports skipped attributed detections as queued work', function () {
    Queue::fake();
    $arguments = ['findings' => [AsyncDetectableFinding::class]];
    $this->artisan('health:detect', $arguments)->assertSuccessful();
    $this->artisan('health:detect', $arguments)
        ->expectsOutputToContain('0 queued, 1 skipped, 0 failed')
        ->assertSuccessful();
    Queue::assertPushed(DetectFinding::class, 1);
});

it('keeps direct detection calls synchronous', function () {
    Queue::fake();
    AsyncDetectableFinding::detect();
    expect(AsyncDetectableFinding::$runs)->toBe(1);
    Queue::assertNothingPushed();
});

it('rejects Async on classes and methods other than detect during discovery', function (string $finding) {
    file_put_contents($this->asyncDirectory.'/'.$finding.'.php', '<?php');
    expect(fn () => (new FindingRegistry)->all())->toThrow(LogicException::class, 'only be applied to detect()');
})->with(['ClassAttributedFinding', 'AsyncVerifyFinding', 'AsyncResolveFinding']);

#[Scheduled('0 * * * *')]
class AsyncDetectableFinding extends ActionableFinding implements CanDetect
{
    public static int $runs = 0;

    #[Async(queue: 'detection', connection: 'redis')]
    public static function detect(): ?int
    {
        self::$runs++;

        return null;
    }
}

#[AutoResolve]
#[AutoVerify(2)]
class AutomaticFinding extends AsyncDetectableFinding {}

class SynchronousFinding extends ActionableFinding implements CanDetect
{
    public static function detect(): ?int
    {
        return null;
    }
}

#[Async]
class ClassAttributedFinding extends ActionableFinding {}

class AsyncVerifyFinding extends ActionableFinding
{
    #[Async]
    public function verify(): bool
    {
        return true;
    }
}

class AsyncResolveFinding extends ActionableFinding
{
    #[Async]
    public function resolve(): bool
    {
        return true;
    }
}
