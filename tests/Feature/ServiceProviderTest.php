<?php

declare(strict_types=1);

use DataHealth\Console\Commands\DataHealthCommand;
use DataHealth\DataHealthCheckCursor;
use DataHealth\DataHealthManager;
use DataHealth\DataHealthServiceProvider;
use DataHealth\Facades\CheckCursor;
use DataHealth\Facades\DataHealth;
use Illuminate\Support\ServiceProvider;

it('merges the package configuration', function () {
    expect(config('data-health.directories'))->toBe([
        'app/DataHealth' => 'App\\DataHealth\\',
    ])->and(config('data-health.scheduler.enabled'))->toBeTrue();
});

it('loads package translations and views', function () {
    expect(trans('data-health::messages.placeholder'))
        ->toBe('DataHealth placeholder translation.')
        ->and(view()->exists('data-health::placeholder'))->toBeTrue();
});

it('registers the package command', function () {
    $this->artisan('data-health:placeholder')
        ->expectsOutputToContain('DataHealth placeholder command executed.')
        ->assertSuccessful();

    expect(app('Illuminate\\Contracts\\Console\\Kernel')->all()['data-health:placeholder'])
        ->toBeInstanceOf(DataHealthCommand::class);
});

it('resolves package services through their facades', function () {
    expect(DataHealth::getFacadeRoot())->toBeInstanceOf(DataHealthManager::class)
        ->and(CheckCursor::getFacadeRoot())->toBeInstanceOf(DataHealthCheckCursor::class);
});

it('registers each documented publish group', function (string $tag) {
    expect(ServiceProvider::pathsToPublish(DataHealthServiceProvider::class, $tag))
        ->not->toBeEmpty();
})->with([
    'all resources' => 'data-health',
    'configuration' => 'data-health-config',
    'migrations' => 'data-health-migrations',
    'views' => 'data-health-views',
    'translations' => 'data-health-lang',
    'assets' => 'data-health-assets',
]);
