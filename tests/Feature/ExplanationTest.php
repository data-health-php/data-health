<?php

declare(strict_types=1);

use DataHealth\DataHealthManager;
use DataHealth\Enums\RecordStatus;
use DataHealth\Tests\Fixtures\DataHealth\BasicFinding;
use DataHealth\Tests\Fixtures\DataHealth\ExplainableFinding;
use DataHealth\Tests\Fixtures\Models\TestModel;

beforeEach(function () {
    $this->freezeTime();
    $this->migrateDataHealthDatabase();
    config()->set('data-health.directories', [
        '../../../../tests/Fixtures/DataHealth' => 'DataHealth\\Tests\\Fixtures\\DataHealth\\',
    ]);
    $this->model = TestModel::create(['name' => 'Original']);
});

it('explains current facts without changing the finding or business data', function () {
    $manager = app(DataHealthManager::class);
    $record = ExplainableFinding::found($this->model);
    $attributes = $record->refresh()->getRawOriginal();
    $this->model->update(['name' => 'Correct']);

    $explanation = $manager->explain($record);

    expect($explanation['actual'])->toBe(['name' => 'Correct'])
        ->and($explanation['still_applies'])->toBeFalse()
        ->and($explanation['observed_at'])->toBe(now()->toIso8601String())
        ->and($record->refresh()->getRawOriginal())->toBe($attributes)
        ->and($this->model->refresh()->name)->toBe('Correct');
});

it('reports unsupported explanations and propagates failures', function () {
    $manager = app(DataHealthManager::class);
    $basic = BasicFinding::found($this->model);
    $failed = ExplainableFinding::found($this->model, ['fail' => true]);

    expect(fn () => $manager->explain($basic))->toThrow(RuntimeException::class, 'does not implement CanExplain')
        ->and(fn () => $manager->explain($failed))->toThrow(RuntimeException::class, 'Explanation is unavailable.')
        ->and($failed->refresh()->status)->toBe(RecordStatus::Active);
});

it('prints an explanation from the console without resolving the record', function () {
    $record = ExplainableFinding::found($this->model);

    $this->artisan('health:explain', ['id' => $record->id])
        ->expectsOutputToContain('The model name should be Correct.')
        ->expectsOutputToContain('"name": "Original"')
        ->assertSuccessful();

    expect($record->refresh()->status)->toBe(RecordStatus::Active);
});

it('reports missing records and explanation failures in the console', function () {
    $record = ExplainableFinding::found($this->model, ['fail' => true]);

    $this->artisan('health:explain', ['id' => 999])->expectsOutput('Finding record 999 was not found.')->assertFailed();
    $this->artisan('health:explain', ['id' => $record->id])->expectsOutput('Explanation is unavailable.')->assertFailed();
});

it('includes explanations when inspecting records and keeps failed records inspectable', function (bool $fail) {
    $record = ExplainableFinding::found($this->model, ['fail' => $fail]);

    $this->artisan('health:records', ['--interactive' => true])
        ->expectsQuestion('Record actions', 'inspect')
        ->expectsQuestion('Select a record to inspect', $record->id)
        ->expectsOutputToContain($fail ? 'Explanation is unavailable.' : '"name": "Original"')
        ->expectsQuestion('Record actions', 'exit')
        ->assertSuccessful();
})->with([false, true]);
