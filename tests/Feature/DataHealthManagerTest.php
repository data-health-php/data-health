<?php

declare(strict_types=1);

use DataHealth\ContextHasher;
use DataHealth\DataHealthManager;
use DataHealth\Enums\FindingUrgency;
use DataHealth\Enums\RecordStatus;
use DataHealth\FindingRegistry;
use DataHealth\Models\FindingRecord;
use DataHealth\Tests\Fixtures\DataHealth\ActionableFinding;
use DataHealth\Tests\Fixtures\DataHealth\AutomaticallyResolvedFinding;
use DataHealth\Tests\Fixtures\DataHealth\BasicFinding;
use DataHealth\Tests\Fixtures\DataHealth\KeyedFinding;
use DataHealth\Tests\Fixtures\DataHealth\UrgentFinding;
use DataHealth\Tests\Fixtures\Models\TestModel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->migrateDataHealthDatabase();

    config()->set('data-health.directories', [
        '../../../../tests/Fixtures/DataHealth' => 'DataHealth\\Tests\\Fixtures\\DataHealth\\',
    ]);

    $this->manager = new DataHealthManager(new FindingRegistry, new ContextHasher);
    $this->model = TestModel::create(['name' => 'Example']);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('records a newly found issue with status context urgency and worklist', function () {
    $record = $this->manager->found(new UrgentFinding(
        $this->model,
        ['reason' => 'duplicate'],
    ));

    expect($record)->toBeInstanceOf(FindingRecord::class)
        ->and($record->status)->toBe(RecordStatus::Active)
        ->and($record->key)->toBe('UrgentFinding')
        ->and($record->model->is($this->model))->toBeTrue()
        ->and($record->context)->toBe(['reason' => 'duplicate'])
        ->and($record->context_hash)->toBe(hash('sha256', '{"reason":"duplicate"}'))
        ->and($record->worklist)->toBe('data-quality')
        ->and($record->urgency)->toBe(FindingUrgency::SOON);
});

it('uses an explicit finding key in the database and registry', function () {
    $record = $this->manager->found(new KeyedFinding($this->model));

    expect($record->key)->toBe('duplicate-customer')
        ->and($this->manager->getFindingForRecord($record))->toBeInstanceOf(KeyedFinding::class);
});

it('treats object key order as the same context', function () {
    $original = $this->manager->found(new UrgentFinding($this->model, [
        'reason' => 'duplicate',
        'meta' => [
            'source' => 'import',
            'attempt' => 1,
        ],
    ]));

    $record = $this->manager->found(new UrgentFinding($this->model, [
        'meta' => [
            'attempt' => 1,
            'source' => 'import',
        ],
        'reason' => 'duplicate',
    ]));

    expect(FindingRecord::query()->count())->toBe(1)
        ->and($record->is($original))->toBeTrue();
});

it('treats additional object properties as a different context', function () {
    $original = $this->manager->found(new UrgentFinding(
        $this->model,
        ['reason' => 'duplicate'],
    ));
    $record = $this->manager->found(new UrgentFinding($this->model, [
        'reason' => 'duplicate',
        'source' => 'import',
    ]));

    expect(FindingRecord::query()->count())->toBe(2)
        ->and($record->isNot($original))->toBeTrue()
        ->and($record->context_hash)->not->toBe($original->context_hash);
});

it('enforces unique finding identities in the database', function () {
    $record = $this->manager->found(new UrgentFinding(
        $this->model,
        ['reason' => 'duplicate'],
    ));

    expect(fn () => $record->replicate()->save())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('uses normal urgency when the finding has no urgency attribute', function () {
    $record = $this->manager->found(new BasicFinding($this->model));

    expect($record->urgency)->toBe(FindingUrgency::NORMAL)
        ->and($record->worklist)->toBeNull();
});

it('refreshes an existing active record instead of creating a duplicate', function () {
    Carbon::setTestNow('2026-01-01 10:00:00');
    $finding = new UrgentFinding($this->model, ['reason' => 'duplicate']);
    $original = $this->manager->found($finding);

    Carbon::setTestNow('2026-01-01 11:00:00');
    $record = $this->manager->found($finding);

    expect(FindingRecord::query()->count())->toBe(1)
        ->and($record->is($original))->toBeTrue()
        ->and($record->updated_at->toDateTimeString())->toBe('2026-01-01 11:00:00');
});

it('reactivates a resolved record when the issue is found again', function () {
    $finding = new UrgentFinding($this->model, ['reason' => 'duplicate']);
    $record = $this->manager->found($finding);
    $record->update(['status' => RecordStatus::Resolved]);

    expect($this->manager->found($finding)->status)->toBe(RecordStatus::Active)
        ->and(FindingRecord::query()->count())->toBe(1);
});

it('leaves an ignored record ignored when the issue is found again', function () {
    $finding = new UrgentFinding($this->model, ['reason' => 'duplicate']);
    $record = $this->manager->found($finding);
    $record->update(['status' => RecordStatus::Ignored]);

    expect($this->manager->found($finding)->status)->toBe(RecordStatus::Ignored)
        ->and(FindingRecord::query()->count())->toBe(1);
});

it('recreates a finding from its persisted record', function () {
    $record = $this->manager->found(new UrgentFinding(
        $this->model,
        ['reason' => 'duplicate'],
    ));

    $finding = $this->manager->getFindingForRecord($record);

    expect($finding)->toBeInstanceOf(UrgentFinding::class)
        ->and($finding->model->is($this->model))->toBeTrue()
        ->and($finding->context)->toBe(['reason' => 'duplicate']);
});

it('automatically resolves new findings marked for automatic resolution', function () {
    $record = $this->manager->found(new AutomaticallyResolvedFinding(
        $this->model,
        ['succeeds' => true],
    ));

    expect($record->status)->toBe(RecordStatus::Resolved);
});

it('keeps automatically resolved findings active when resolution fails', function () {
    $record = $this->manager->found(new AutomaticallyResolvedFinding(
        $this->model,
        ['succeeds' => false],
    ));

    expect($record->status)->toBe(RecordStatus::Active);
});

it('uses boolean results returned by actionable findings', function (string $operation, bool $succeeds) {
    $record = $this->manager->found(new ActionableFinding($this->model, [
        'scenario' => $operation.':boolean:'.($succeeds ? 'true' : 'false'),
    ]));

    expect($this->manager->{$operation}($record))->toBe($succeeds)
        ->and($record->fresh()->status)->toBe(
            $succeeds ? RecordStatus::Resolved : RecordStatus::Active,
        );
})->with([
    ['resolve', true],
    ['resolve', false],
    ['verify', true],
    ['verify', false],
]);

it('invokes callable results returned by actionable findings', function (string $operation, bool $succeeds) {
    $record = $this->manager->found(new ActionableFinding($this->model, [
        'scenario' => $operation.':callable:'.($succeeds ? 'true' : 'false'),
    ]));

    expect($this->manager->{$operation}($record))->toBe($succeeds)
        ->and($record->fresh()->status)->toBe(
            $succeeds ? RecordStatus::Resolved : RecordStatus::Active,
        );
})->with([
    ['resolve', true],
    ['resolve', false],
    ['verify', true],
    ['verify', false],
]);

it('resolves handler classes returned by actionable findings', function (string $operation, bool $succeeds) {
    $record = $this->manager->found(new ActionableFinding($this->model, [
        'scenario' => $operation.':handler:'.($succeeds ? 'true' : 'false'),
    ]));

    expect($this->manager->{$operation}($record))->toBe($succeeds)
        ->and($record->fresh()->status)->toBe(
            $succeeds ? RecordStatus::Resolved : RecordStatus::Active,
        );
})->with([
    ['resolve', true],
    ['resolve', false],
    ['verify', true],
    ['verify', false],
]);

it('rejects operations unsupported by the finding', function (string $operation, string $contract) {
    $record = $this->manager->found(new BasicFinding($this->model));

    expect(fn () => $this->manager->{$operation}($record))
        ->toThrow(RuntimeException::class, BasicFinding::class." does not implement {$contract}");
})->with([
    ['resolve', 'CanResolve'],
    ['verify', 'CanVerify'],
]);

it('rejects invalid handler results', function (string $operation, string $contract) {
    $record = $this->manager->found(new ActionableFinding($this->model, [
        'scenario' => $operation.':invalid:false',
    ]));

    expect(fn () => $this->manager->{$operation}($record))
        ->toThrow(RuntimeException::class, ActionableFinding::class." does not implement {$contract}");
})->with([
    ['resolve', 'Resolver'],
    ['verify', 'Verifier'],
]);

it('creates records through the finding convenience method', function () {
    app()->instance(DataHealthManager::class, $this->manager);

    $record = UrgentFinding::found($this->model, ['reason' => 'static']);

    expect($record->key)->toBe('UrgentFinding')
        ->and($record->context)->toBe(['reason' => 'static']);
});

it('returns the runtime finding from a record', function () {
    app()->instance(DataHealthManager::class, $this->manager);
    $record = $this->manager->found(new UrgentFinding($this->model));

    expect($record->getFinding())->toBeInstanceOf(UrgentFinding::class);
});
