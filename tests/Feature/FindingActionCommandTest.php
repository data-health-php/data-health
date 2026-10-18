<?php

declare(strict_types=1);

use DataHealth\DataHealthManager;
use DataHealth\Enums\RecordStatus;
use DataHealth\Tests\Fixtures\DataHealth\ActionableFinding;
use DataHealth\Tests\Fixtures\DataHealth\BasicFinding;
use DataHealth\Tests\Fixtures\Models\TestModel;

beforeEach(function () {
    $this->migrateDataHealthDatabase();

    config()->set('data-health.directories', [
        '../../../../tests/Fixtures/DataHealth' => 'DataHealth\\Tests\\Fixtures\\DataHealth\\',
    ]);

    $this->model = TestModel::create(['name' => 'Example']);
});

it('evaluates finding action results for the requested record', function (string $operation, string $result, bool $succeeds) {
    $manager = app(DataHealthManager::class);
    $otherRecord = $manager->found(new ActionableFinding($this->model, ['other' => true]));
    $record = $manager->found(new ActionableFinding($this->model, [
        'scenario' => $operation.':'.$result.':'.($succeeds ? 'true' : 'false'),
    ]));
    $action = $operation === 'verify' ? 'verified' : 'resolved';

    $this->artisan($operation, ['id' => (string) $record->id])
        ->expectsOutput($succeeds
            ? "Finding record {$record->id} {$action}."
            : "Finding record {$record->id} could not be {$action}.")
        ->assertExitCode($succeeds ? 0 : 1);

    expect($record->fresh()->status)->toBe($succeeds ? RecordStatus::Resolved : RecordStatus::Active)
        ->and($otherRecord->fresh()->status)->toBe(RecordStatus::Active);
})->with(['verify', 'resolve'])->with(['boolean', 'callable', 'handler'])->with([true, false]);

it('reports a missing finding record', function (string $operation) {
    $this->artisan($operation, ['id' => '999'])
        ->expectsOutput('Finding record 999 was not found.')
        ->assertFailed();
})->with(['verify', 'resolve']);

it('exposes namespaced aliases for finding actions', function (string $operation) {
    $record = app(DataHealthManager::class)->found(new ActionableFinding($this->model, [
        'scenario' => $operation.':boolean:true',
    ]));

    $this->artisan('health:'.$operation, ['id' => (string) $record->id])->assertSuccessful();

    expect($record->fresh()->status)->toBe(RecordStatus::Resolved);
})->with(['verify', 'resolve']);

it('reports unsupported finding actions', function (string $operation, string $contract) {
    $record = app(DataHealthManager::class)->found(new BasicFinding($this->model));

    $this->artisan($operation, ['id' => (string) $record->id])
        ->expectsOutput(BasicFinding::class." does not implement {$contract}")
        ->assertFailed();

    expect($record->fresh()->status)->toBe(RecordStatus::Active);
})->with([
    ['verify', 'CanVerify'],
    ['resolve', 'CanResolve'],
]);

it('reports exceptions raised while evaluating an action', function (string $operation, string $contract) {
    $record = app(DataHealthManager::class)->found(new ActionableFinding($this->model, [
        'scenario' => $operation.':invalid:false',
    ]));

    $this->artisan($operation, ['id' => (string) $record->id])
        ->expectsOutput(ActionableFinding::class." does not implement {$contract}")
        ->assertFailed();

    expect($record->fresh()->status)->toBe(RecordStatus::Active);
})->with([
    ['verify', 'Verifier'],
    ['resolve', 'Resolver'],
]);
