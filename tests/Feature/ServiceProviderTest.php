<?php

declare(strict_types=1);

use DataHealth\DataHealthCheckCursor;
use DataHealth\DataHealthManager;
use DataHealth\DataHealthScheduler;
use DataHealth\DataHealthServiceProvider;
use DataHealth\Facades\CheckCursor;
use DataHealth\Facades\DataHealth;
use Illuminate\Support\ServiceProvider;

it('merges the package configuration', function () {
    expect(config('data-health.directories'))->toBe([
        'app/DataHealth' => 'App\\DataHealth\\',
    ])->and(config('data-health.scheduler.enabled'))->toBeTrue()
        ->and(config('data-health.auto_delete.enabled'))->toBeFalse();
});

it('does not register a wildcard model listener when auto delete is disabled', function () {
    expect(app('events')->hasWildcardListeners('eloquent.deleted: App\\Models\\Customer'))
        ->toBeFalse();
});

it('resolves package services through their facades', function () {
    expect(DataHealth::getFacadeRoot())->toBeInstanceOf(DataHealthManager::class)
        ->and(CheckCursor::getFacadeRoot())->toBeInstanceOf(DataHealthCheckCursor::class);
});

it('registers scheduled detections when routes are cached', function () {
    $scheduler = Mockery::mock(DataHealthScheduler::class);
    $scheduler->expects('schedule')->once();

    app()->instance('routes.cached', true);
    app()->instance(DataHealthScheduler::class, $scheduler);

    (new DataHealthServiceProvider(app()))->boot();
});

it('registers each documented publish group', function (string $tag) {
    expect(ServiceProvider::pathsToPublish(DataHealthServiceProvider::class, $tag))
        ->not->toBeEmpty();
})->with([
    'all resources' => 'data-health',
    'configuration' => 'data-health-config',
    'migrations' => 'data-health-migrations',
]);
