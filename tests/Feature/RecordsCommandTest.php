<?php

declare(strict_types=1);

use DataHealth\Console\Commands\RecordsCommand;
use DataHealth\DataHealthManager;
use DataHealth\Enums\RecordStatus;
use DataHealth\Models\FindingRecord;
use DataHealth\Tests\Fixtures\DataHealth\ActionableFinding;
use DataHealth\Tests\Fixtures\DataHealth\BasicFinding;
use DataHealth\Tests\Fixtures\Models\TestModel;
use Illuminate\Contracts\Console\Kernel;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

beforeEach(function () {
    $this->migrateDataHealthDatabase();
    config()->set('data-health.directories', [
        '../../../../tests/Fixtures/DataHealth' => 'DataHealth\\Tests\\Fixtures\\DataHealth\\',
    ]);
    $this->model = TestModel::create(['name' => 'Example']);
});

it('lists active records with their model status and available actions', function () {
    $manager = app(DataHealthManager::class);
    $record = $manager->found(new ActionableFinding($this->model));
    $ignored = $manager->found(new BasicFinding($this->model));
    $ignored->update(['status' => RecordStatus::Ignored]);

    $this->artisan('health:records')
        ->expectsTable(['ID', 'Finding', 'Model', 'Status', 'Urgency', 'Actions'], [[
            $record->id, 'ActionableFinding', $record->model_type.':'.$record->model_id,
            'active', 'normal', 'ignore, solve, verify, resolve',
        ]])
        ->expectsOutput('Page 1 of 1 (1 records).')
        ->assertSuccessful();
});

it('opens the record browser by default in a terminal', function () {
    app(DataHealthManager::class)->found(new BasicFinding($this->model));
    app(Kernel::class)->registerCommand(new class extends RecordsCommand
    {
        protected function hasTerminal(): bool
        {
            return true;
        }
    });

    $this->artisan('health:records')
        ->expectsQuestion('Record actions', 'exit')
        ->assertSuccessful();
});

it('prints records without prompting when no interaction is requested in a terminal', function () {
    app(DataHealthManager::class)->found(new BasicFinding($this->model));
    app(Kernel::class)->registerCommand(new class extends RecordsCommand
    {
        protected function hasTerminal(): bool
        {
            return true;
        }
    });

    $this->artisan('health:records', ['--no-interaction' => true])
        ->expectsOutput('Page 1 of 1 (1 records).')
        ->assertSuccessful();
});

it('filters records by status and finding class', function () {
    $manager = app(DataHealthManager::class);
    $record = $manager->found(new ActionableFinding($this->model));
    $record->update(['status' => RecordStatus::Resolved]);
    $manager->found(new BasicFinding($this->model));

    $this->artisan('health:records', ['--status' => 'resolved', '--finding' => ActionableFinding::class])
        ->expectsOutputToContain('ActionableFinding')
        ->doesntExpectOutputToContain('BasicFinding')
        ->assertSuccessful();
});

it('supports pagination and all statuses without prompting', function () {
    $manager = app(DataHealthManager::class);
    $manager->found(new BasicFinding($this->model));
    $record = $manager->found(new ActionableFinding($this->model));
    $record->update(['status' => RecordStatus::Ignored]);

    $this->artisan('health:records', ['--status' => 'all', '--page' => '2', '--per-page' => '1'])
        ->expectsOutputToContain('ActionableFinding')
        ->doesntExpectOutputToContain('BasicFinding')
        ->expectsOutput('Page 2 of 2 (2 records).')
        ->assertSuccessful();
});

it('reports an empty record list', function () {
    $this->artisan('health:records', ['--interactive' => true])
        ->expectsOutput('No finding records match these filters.')
        ->assertSuccessful();
});

it('validates record browser options', function (array $options, string $message) {
    $this->artisan('health:records', $options)
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    [['--status' => 'unknown'], '--status must be active, ignored, resolved, or all.'],
    [['--status' => ''], '--status must be active, ignored, resolved, or all.'],
    [['--per-page' => '0'], '--per-page must be between 1 and 100.'],
    [['--per-page' => '101'], '--per-page must be between 1 and 100.'],
    [['--page' => 'abc'], '--page must be a positive integer.'],
    [['--page' => '0'], '--page must be a positive integer.'],
    [['--finding' => ''], '--finding requires a finding key or class name.'],
    [['--finding' => 'MissingFinding'], 'Finding with key or class MissingFinding not found'],
    [['--interactive' => true, '--no-interaction' => true], '--interactive requires an interactive terminal.'],
]);

it('runs the chosen action on selected records and refreshes the list', function (string $operation) {
    $manager = app(DataHealthManager::class);
    $first = $manager->found(new ActionableFinding($this->model, ['scenario' => $operation.':boolean:true']));
    $second = $manager->found(new ActionableFinding($this->model, ['scenario' => $operation.':handler:true']));
    $other = $manager->found(new BasicFinding($this->model));

    $this->artisan('health:records', ['--interactive' => true])
        ->expectsQuestion('Record actions', $operation)
        ->expectsQuestion('Select records to '.$operation, [$first->id, $second->id])
        ->expectsOutput('Action summary: 2 succeeded, 0 failed.')
        ->expectsQuestion('Record actions', 'exit')
        ->assertSuccessful();

    expect($first->fresh()->status)->toBe(RecordStatus::Resolved)
        ->and($second->fresh()->status)->toBe(RecordStatus::Resolved)
        ->and($other->fresh()->status)->toBe(RecordStatus::Active);
})->with(['verify', 'resolve']);

it('continues after unsuccessful record actions and preserves a failing exit code', function () {
    $manager = app(DataHealthManager::class);
    $failed = $manager->found(new ActionableFinding($this->model, ['scenario' => 'verify:boolean:false']));
    $exception = $manager->found(new ActionableFinding($this->model, ['scenario' => 'verify:invalid:false']));
    $passed = $manager->found(new ActionableFinding($this->model, ['scenario' => 'verify:boolean:true']));

    $this->artisan('health:records', ['--interactive' => true])
        ->expectsQuestion('Record actions', 'verify')
        ->expectsQuestion('Select records to verify', [$failed->id, $exception->id, $passed->id])
        ->expectsOutput('Action summary: 1 succeeded, 2 failed.')
        ->expectsQuestion('Record actions', 'exit')
        ->assertFailed();

    expect($failed->fresh()->status)->toBe(RecordStatus::Active)
        ->and($exception->fresh()->status)->toBe(RecordStatus::Active)
        ->and($passed->fresh()->status)->toBe(RecordStatus::Resolved);
});

it('can browse between pages before inspecting record context', function () {
    $manager = app(DataHealthManager::class);
    $manager->found(new BasicFinding($this->model));
    $record = $manager->found(new ActionableFinding($this->model, ['reason' => 'duplicate']));

    $this->artisan('health:records', ['--interactive' => true, '--per-page' => '1'])
        ->expectsQuestion('Record actions', 'next')
        ->expectsQuestion('Record actions', 'inspect')
        ->expectsQuestion('Select a record to inspect', $record->id)
        ->expectsOutputToContain('"reason": "duplicate"')
        ->expectsQuestion('Record actions', 'previous')
        ->expectsQuestion('Record actions', 'exit')
        ->assertSuccessful();
});

it('does not offer unsupported records for an action', function () {
    app(DataHealthManager::class)->found(new BasicFinding($this->model));

    $this->artisan('health:records', ['--interactive' => true])
        ->expectsQuestion('Record actions', 'resolve')
        ->expectsOutput('No records on this page support resolve.')
        ->expectsQuestion('Record actions', 'exit')
        ->assertSuccessful();
});

it('allows cancelling a record selection without running actions', function () {
    $record = app(DataHealthManager::class)->found(new ActionableFinding($this->model, ['scenario' => 'resolve:boolean:true']));

    $this->artisan('health:records', ['--interactive' => true])
        ->expectsQuestion('Record actions', 'resolve')
        ->expectsQuestion('Select records to resolve', [])
        ->expectsQuestion('Record actions', 'exit')
        ->assertSuccessful();

    expect($record->fresh()->status)->toBe(RecordStatus::Active);
});

it('adjusts the page when actions remove the final page from the active list', function () {
    $manager = app(DataHealthManager::class);
    $manager->found(new BasicFinding($this->model));
    $record = $manager->found(new ActionableFinding($this->model, ['scenario' => 'resolve:boolean:true']));

    $this->artisan('health:records', ['--interactive' => true, '--page' => '2', '--per-page' => '1'])
        ->expectsOutput('Page 2 of 2 (2 records).')
        ->expectsQuestion('Record actions', 'resolve')
        ->expectsQuestion('Select records to resolve', [$record->id])
        ->expectsOutput('Page 1 of 1 (1 records).')
        ->expectsQuestion('Record actions', 'exit')
        ->assertSuccessful();
});

it('finishes the browser when no matching records remain after actions', function () {
    $record = app(DataHealthManager::class)->found(new ActionableFinding($this->model, ['scenario' => 'verify:boolean:true']));

    $this->artisan('health:records', ['--interactive' => true])
        ->expectsQuestion('Record actions', 'verify')
        ->expectsQuestion('Select records to verify', [$record->id])
        ->expectsOutput('No finding records match these filters.')
        ->assertSuccessful();
});

it('keeps records with unregistered finding keys available for inspection', function () {
    $record = app(DataHealthManager::class)->found(new BasicFinding($this->model));
    $record->update(['key' => 'MissingFinding']);

    $this->artisan('health:records', ['--interactive' => true])
        ->expectsQuestion('Record actions', 'inspect')
        ->expectsQuestion('Select a record to inspect', $record->id)
        ->expectsOutputToContain('"key": "MissingFinding"')
        ->expectsQuestion('Record actions', 'exit')
        ->assertSuccessful();
});

it('resolves checked table rows with a keyboard shortcut', function () {
    $manager = app(DataHealthManager::class);
    $first = $manager->found(new ActionableFinding($this->model, ['scenario' => 'resolve:boolean:true']));
    $second = $manager->found(new ActionableFinding($this->model, ['scenario' => 'resolve:handler:true']));
    $other = $manager->found(new BasicFinding($this->model));

    // Exercise native key input instead of Laravel's unit-test menu fallback.
    app(Kernel::class)->registerCommand(new class extends RecordsCommand
    {
        protected function useInteractiveTable(): bool
        {
            return true;
        }
    });
    Prompt::fake([Key::SPACE, Key::DOWN, Key::SPACE, 'r', 'q']);

    $this->artisan('health:records', ['--interactive' => true])
        ->expectsOutput('Action summary: 2 succeeded, 0 failed.')
        ->assertSuccessful();

    expect($first->fresh()->status)->toBe(RecordStatus::Resolved)
        ->and($second->fresh()->status)->toBe(RecordStatus::Resolved)
        ->and($other->fresh()->status)->toBe(RecordStatus::Active);
});

it('skips unsupported checked rows while verifying supported rows', function () {
    $manager = app(DataHealthManager::class);
    $record = $manager->found(new ActionableFinding($this->model, ['scenario' => 'verify:boolean:true']));
    $other = $manager->found(new BasicFinding($this->model));
    app(Kernel::class)->registerCommand(new class extends RecordsCommand
    {
        protected function useInteractiveTable(): bool
        {
            return true;
        }
    });
    Prompt::fake(['a', 'v', 'q']);

    $this->artisan('health:records', ['--interactive' => true])
        ->expectsOutput("Skipped record {$other->id}: verify is not supported.")
        ->expectsOutput('Action summary: 1 succeeded, 0 failed.')
        ->assertSuccessful();

    expect($record->fresh()->status)->toBe(RecordStatus::Resolved)
        ->and($other->fresh()->status)->toBe(RecordStatus::Active);
});

it('navigates pages and inspects the highlighted record with table keys', function () {
    $manager = app(DataHealthManager::class);
    $manager->found(new BasicFinding($this->model));
    $manager->found(new ActionableFinding($this->model, ['reason' => 'keyboard inspection']));
    app(Kernel::class)->registerCommand(new class extends RecordsCommand
    {
        protected function useInteractiveTable(): bool
        {
            return true;
        }
    });
    Prompt::fake(['n', Key::ENTER, 'p', 'q']);

    $this->artisan('health:records', ['--interactive' => true, '--per-page' => '1'])
        ->expectsOutputToContain('"reason": "keyboard inspection"')
        ->assertSuccessful();
});

it('falls back to menus when the terminal cannot capture raw keys', function () {
    app(DataHealthManager::class)->found(new BasicFinding($this->model));
    app(Kernel::class)->registerCommand(new class extends RecordsCommand
    {
        protected function useInteractiveTable(): bool
        {
            return true;
        }
    });
    Prompt::fake();
    Prompt::terminal()->shouldReceive('setTty')->andThrow(new RuntimeException('Raw input unavailable.'));

    $this->artisan('health:records', ['--interactive' => true])
        ->expectsQuestion('Record actions', 'exit')
        ->assertSuccessful();
});

it('ignores checked records or the highlighted record without requiring a finding action contract', function (array $keys, bool $firstIgnored) {
    $manager = app(DataHealthManager::class);
    $first = $manager->found(new BasicFinding($this->model));
    $second = $manager->found(new ActionableFinding($this->model));
    $second->update(['key' => 'MissingFinding']);
    $other = $manager->found(new BasicFinding($this->model, ['other' => true]));

    app(Kernel::class)->registerCommand(new class extends RecordsCommand
    {
        protected function useInteractiveTable(): bool
        {
            return true;
        }
    });
    Prompt::fake($keys);

    $this->artisan('health:records', ['--interactive' => true])
        ->expectsOutput("Finding record {$second->id} ignored.")
        ->assertSuccessful();

    expect($first->fresh()->status)->toBe($firstIgnored ? RecordStatus::Ignored : RecordStatus::Active)
        ->and($second->fresh()->status)->toBe(RecordStatus::Ignored)
        ->and($other->fresh()->status)->toBe(RecordStatus::Active);
})->with([
    'checked rows' => [[Key::SPACE, Key::DOWN, Key::SPACE, 'i', 'q'], true],
    'highlighted row' => [[Key::DOWN, 'i', 'q'], false],
]);

it('offers ignore in the fallback menu and refreshes active records', function () {
    $record = app(DataHealthManager::class)->found(new BasicFinding($this->model));

    $this->artisan('health:records', ['--interactive' => true])
        ->expectsQuestion('Record actions', 'ignore')
        ->expectsQuestion('Select records to ignore', [$record->id])
        ->expectsOutput("Finding record {$record->id} ignored.")
        ->expectsOutput('No finding records match these filters.')
        ->assertSuccessful();

    expect($record->fresh()->status)->toBe(RecordStatus::Ignored);
});

it('skips resolved records when ignoring a table selection', function () {
    $manager = app(DataHealthManager::class);
    $active = $manager->found(new BasicFinding($this->model));
    $resolved = $manager->found(new ActionableFinding($this->model));
    $resolved->update(['status' => RecordStatus::Resolved]);
    app(Kernel::class)->registerCommand(new class extends RecordsCommand
    {
        protected function useInteractiveTable(): bool
        {
            return true;
        }
    });
    Prompt::fake(['a', 'i', 'q']);

    $this->artisan('health:records', ['--interactive' => true, '--status' => 'all'])
        ->expectsOutput("Skipped record {$resolved->id}: ignore is not supported.")
        ->assertSuccessful();

    expect($active->fresh()->status)->toBe(RecordStatus::Ignored)
        ->and($resolved->fresh()->status)->toBe(RecordStatus::Resolved);
});

it('continues ignoring other records after a save fails and returns a failure exit code', function () {
    $manager = app(DataHealthManager::class);
    $failed = $manager->found(new BasicFinding($this->model));
    $passed = $manager->found(new ActionableFinding($this->model));
    FindingRecord::updating(function (FindingRecord $record) use ($failed): void {
        if ($record->id === $failed->id) {
            throw new RuntimeException('Ignore failed.');
        }
    });

    $this->artisan('health:records', ['--interactive' => true])
        ->expectsQuestion('Record actions', 'ignore')
        ->expectsQuestion('Select records to ignore', [$failed->id, $passed->id])
        ->expectsOutput('Ignore failed.')
        ->expectsOutput('Action summary: 1 succeeded, 1 failed.')
        ->expectsQuestion('Record actions', 'exit')
        ->assertFailed();

    expect($failed->fresh()->status)->toBe(RecordStatus::Active)
        ->and($passed->fresh()->status)->toBe(RecordStatus::Ignored);
});

it('marks checked records or the highlighted record as solved without executing resolution logic', function (array $keys, bool $firstSolved) {
    $manager = app(DataHealthManager::class);
    $first = $manager->found(new BasicFinding($this->model));
    $second = $manager->found(new ActionableFinding($this->model, ['scenario' => 'resolve:invalid:false']));
    $other = $manager->found(new BasicFinding($this->model, ['other' => true]));

    app(Kernel::class)->registerCommand(new class extends RecordsCommand
    {
        protected function useInteractiveTable(): bool
        {
            return true;
        }
    });
    Prompt::fake($keys);

    $this->artisan('health:records', ['--interactive' => true])
        ->expectsOutput("Finding record {$second->id} marked as solved.")
        ->assertSuccessful();

    expect($first->fresh()->status)->toBe($firstSolved ? RecordStatus::Resolved : RecordStatus::Active)
        ->and($second->fresh()->status)->toBe(RecordStatus::Resolved)
        ->and($other->fresh()->status)->toBe(RecordStatus::Active)
        ->and($this->model->fresh()->name)->toBe('Example');
})->with([
    'checked rows' => [[Key::SPACE, Key::DOWN, Key::SPACE, 's', 'q'], true],
    'highlighted row' => [[Key::DOWN, 's', 'q'], false],
]);

it('marks an ignored record with an unregistered finding as solved from the fallback menu', function () {
    $record = app(DataHealthManager::class)->found(new BasicFinding($this->model));
    $record->update(['status' => RecordStatus::Ignored, 'key' => 'MissingFinding']);

    $this->artisan('health:records', ['--interactive' => true, '--status' => 'ignored'])
        ->expectsQuestion('Record actions', 'solve')
        ->expectsQuestion('Select records to mark as solved', [$record->id])
        ->expectsOutput("Finding record {$record->id} marked as solved.")
        ->expectsOutput('No finding records match these filters.')
        ->assertSuccessful();

    expect($record->fresh()->status)->toBe(RecordStatus::Resolved);
});

it('skips records already resolved when marking rows as solved', function () {
    $manager = app(DataHealthManager::class);
    $active = $manager->found(new BasicFinding($this->model));
    $resolved = $manager->found(new ActionableFinding($this->model));
    $resolved->update(['status' => RecordStatus::Resolved]);
    app(Kernel::class)->registerCommand(new class extends RecordsCommand
    {
        protected function useInteractiveTable(): bool
        {
            return true;
        }
    });
    Prompt::fake(['a', 's', 'q']);

    $this->artisan('health:records', ['--interactive' => true, '--status' => 'all'])
        ->expectsOutput("Skipped record {$resolved->id}: solve is not supported.")
        ->assertSuccessful();

    expect($active->fresh()->status)->toBe(RecordStatus::Resolved)
        ->and($resolved->fresh()->status)->toBe(RecordStatus::Resolved);
});
