<?php

declare(strict_types=1);

it('opens and exits the Data Health suite', function () {
    $this->artisan('health')
        ->expectsOutputToContain('Data Health')
        ->expectsQuestion('What would you like to do?', 'exit')
        ->assertSuccessful();
});

it('launches interactive detection and returns to the menu', function () {
    config()->set('data-health.directories', []);

    $this->artisan('health')
        ->expectsQuestion('What would you like to do?', 'detect')
        ->expectsOutput('No detectable findings selected.')
        ->expectsQuestion('What would you like to do?', 'exit')
        ->assertSuccessful();
});

it('launches the interactive record browser', function () {
    $this->migrateDataHealthDatabase();

    $this->artisan('health')
        ->expectsQuestion('What would you like to do?', 'records')
        ->expectsOutput('No finding records match these filters.')
        ->expectsQuestion('What would you like to do?', 'exit')
        ->assertSuccessful();
});

it('requires interaction for the suite menu', function () {
    $this->artisan('health', ['--no-interaction' => true])
        ->expectsOutputToContain('Use health:detect or health:records for non-interactive runs.')
        ->assertFailed();
});

it('preserves failures when returning to the main menu', function () {
    // Browsing without migrated tables fails in the child command.
    $this->artisan('health')
        ->expectsQuestion('What would you like to do?', 'records')
        ->expectsQuestion('What would you like to do?', 'exit')
        ->assertFailed();
});
