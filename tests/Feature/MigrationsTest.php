<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('creates and rolls back the package tables', function () {
    $findingMigration = require __DIR__.'/../../database/migrations/2026_01_01_000000_create_data_health_findings_table.php';
    $cursorMigration = require __DIR__.'/../../database/migrations/2026_01_02_000000_create_data_health_cursors_table.php';

    $findingMigration->up();
    $cursorMigration->up();

    expect(Schema::hasColumns('data_health_findings', [
        'id',
        'status',
        'key',
        'model_type',
        'model_id',
        'context',
        'worklist',
        'urgency',
        'created_at',
        'updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('data_health_cursors', [
            'id',
            'key',
            'last_id',
            'created_at',
            'updated_at',
        ]))->toBeTrue();

    $cursorMigration->down();
    $findingMigration->down();

    expect(Schema::hasTable('data_health_cursors'))->toBeFalse()
        ->and(Schema::hasTable('data_health_findings'))->toBeFalse();
});
