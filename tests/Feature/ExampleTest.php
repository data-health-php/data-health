<?php

declare(strict_types=1);

use DataHealth\DataHealth\DataHealth;

it('resolves the singleton', function () {
    expect(app(DataHealth::class))->toBeInstanceOf(DataHealth::class);
});

it('returns the same instance from the container', function () {
    expect(app(DataHealth::class))->toBe(app(DataHealth::class));
});

it('merges the package config', function () {
    expect(config('data-health.placeholder'))->toBe('default');
});

it('loads the package translations', function () {
    expect(trans('data-health::messages.placeholder'))->toBe('DataHealth placeholder translation.');
});

it('loads the package views', function () {
    expect(view()->exists('data-health::placeholder'))->toBeTrue();
});

it('registers the artisan command', function () {
    $this->artisan('data-health:placeholder')
        ->expectsOutputToContain('DataHealth placeholder command executed.')
        ->assertSuccessful();
});
