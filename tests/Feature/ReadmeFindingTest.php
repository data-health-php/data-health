<?php

declare(strict_types=1);

use DataHealth\Enums\RecordStatus;
use DataHealth\Facades\DataHealth;
use DataHealth\Models\FindingRecord;
use DataHealth\Tests\Fixtures\Models\PlainTestModel;
use DataHealth\Tests\Fixtures\Models\TestModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->migrateDataHealthDatabase();

    Schema::table('data_health_test_models', function (Blueprint $table) {
        $table->timestamp('paid_at')->nullable();
        $table->string('status')->default('pending');
    });

    // Execute the published example with a test model rather than duplicating its logic.
    preg_match('/```php\n(.*?)\n```/s', file_get_contents(__DIR__.'/../../README.md'), $matches);

    $this->readmeDirectory = sys_get_temp_dir().'/data-health-readme-'.bin2hex(random_bytes(8));
    $name = 'ReadmeFinding'.bin2hex(random_bytes(8));
    $source = str_replace(
        ['namespace App\\DataHealth;', 'use App\\Models\\Order;', 'PaidOrderMarkedPending'],
        ['namespace DataHealth\\Tests\\ReadmeFindings;', 'use '.TestModel::class.' as Order;', $name],
        $matches[1],
    );

    (new Filesystem)->ensureDirectoryExists($this->readmeDirectory.'/findings');
    $path = $this->readmeDirectory.'/findings/'.$name.'.php';
    file_put_contents($path, $source);
    require $path;

    app()->setBasePath($this->readmeDirectory);
    config()->set('data-health.directories', ['findings' => 'DataHealth\\Tests\\ReadmeFindings\\']);
    $this->readmeFinding = 'DataHealth\\Tests\\ReadmeFindings\\'.$name;
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->readmeDirectory);
});

it('detects repeatable occurrences and verifies only the corrected record', function () {
    $first = TestModel::create(['name' => 'First', 'paid_at' => now()]);
    $second = TestModel::create(['name' => 'Second', 'paid_at' => now()]);
    TestModel::create(['name' => 'Unpaid']);
    TestModel::create(['name' => 'Already paid', 'paid_at' => now(), 'status' => 'paid']);

    $finding = $this->readmeFinding;
    $finding::detect();
    $finding::detect();

    expect(FindingRecord::query()->count())->toBe(2);

    $record = FindingRecord::query()->where('model_id', $first->id)->firstOrFail();
    expect(DataHealth::verify($record))->toBeFalse()
        ->and($first->fresh()->status)->toBe('pending');

    $first->update(['status' => 'paid']);

    expect(DataHealth::verify($record))->toBeTrue()
        ->and($record->refresh()->status)->toBe(RecordStatus::Resolved)
        ->and(FindingRecord::query()->count())->toBe(2)
        ->and(FindingRecord::query()->where('model_id', $second->id)->firstOrFail()->status)->toBe(RecordStatus::Active)
        ->and($second->fresh()->status)->toBe('pending');
});

it('corrects the documented inconsistency and safely repeats resolution', function () {
    $order = TestModel::create(['name' => 'Paid pending', 'paid_at' => now()]);
    $record = $this->readmeFinding::found($order);

    expect(DataHealth::resolve($record))->toBeTrue()
        ->and($order->refresh()->status)->toBe('paid')
        ->and($record->refresh()->status)->toBe(RecordStatus::Resolved)
        ->and(DataHealth::resolve($record))->toBeTrue()
        ->and($order->fresh()->status)->toBe('paid');
});

it('preserves valid changes made after detection', function (array $changes) {
    $order = TestModel::create(['name' => 'Changed later', 'paid_at' => now()]);
    $record = $this->readmeFinding::found($order);
    $order->update($changes);

    expect(DataHealth::resolve($record))->toBeTrue()
        ->and($record->refresh()->status)->toBe(RecordStatus::Resolved)
        ->and($order->fresh()->only(array_keys($changes)))->toBe($changes);
})->with([
    'cancelled order' => [['status' => 'cancelled']],
    'already corrected' => [['status' => 'paid']],
    'payment removed' => [['paid_at' => null]],
]);

it('rejects unexpected models without resolving the record', function (string $operation) {
    $model = PlainTestModel::create(['name' => 'Unexpected model', 'paid_at' => now()]);
    $record = $this->readmeFinding::found($model);

    expect(fn () => DataHealth::$operation($record))
        ->toThrow(LogicException::class, 'Expected an Order model.');

    expect($record->refresh()->status)->toBe(RecordStatus::Active)
        ->and($model->fresh()->status)->toBe('pending');
})->with(['verify', 'resolve']);
