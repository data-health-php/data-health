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

    $this->documentationDirectory = sys_get_temp_dir().'/data-health-documentation-'.bin2hex(random_bytes(8));
    (new Filesystem)->ensureDirectoryExists($this->documentationDirectory.'/findings');

    // Execute the published examples with a test model rather than duplicating their logic.
    foreach ([
        'readmeFinding' => 'README.md',
        'completeFinding' => 'resources/boost/skills/data-health-development/SKILL.md',
    ] as $property => $document) {
        preg_match('/```php\n(.*?)\n```/s', file_get_contents(__DIR__.'/../../'.$document), $matches);

        $name = 'DocumentedFinding'.bin2hex(random_bytes(8));
        $source = str_replace(
            ['namespace App\\DataHealth;', 'use App\\Models\\Order;', 'PaidOrderMarkedPending'],
            ['namespace DataHealth\\Tests\\DocumentedFindings;', 'use '.TestModel::class.' as Order;', $name],
            $matches[1],
        );

        $path = $this->documentationDirectory.'/findings/'.$name.'.php';
        file_put_contents($path, $source);
        require $path;

        $this->$property = 'DataHealth\\Tests\\DocumentedFindings\\'.$name;
    }

    app()->setBasePath($this->documentationDirectory);
    config()->set('data-health.directories', ['findings' => 'DataHealth\\Tests\\DocumentedFindings\\']);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->documentationDirectory);
});

it('runs the readme detector without changing orders and reopens recurring findings', function () {
    $order = TestModel::create(['name' => 'Paid pending', 'paid_at' => now()]);
    TestModel::create(['name' => 'Unpaid']);
    TestModel::create(['name' => 'Already paid', 'paid_at' => now(), 'status' => 'paid']);

    $finding = $this->readmeFinding;
    $finding::detect();
    $finding::detect();

    $record = FindingRecord::query()->sole();

    expect($record->status)->toBe(RecordStatus::Active)
        ->and($record->model->is($order))->toBeTrue()
        ->and($order->fresh()->status)->toBe('pending');

    $record->update(['status' => RecordStatus::Resolved]);
    $finding::detect();

    expect(FindingRecord::query()->sole()->id)->toBe($record->id)
        ->and($record->refresh()->status)->toBe(RecordStatus::Active);

    $record->update(['status' => RecordStatus::Ignored]);
    $finding::detect();

    expect(FindingRecord::query()->sole()->id)->toBe($record->id)
        ->and($record->refresh()->status)->toBe(RecordStatus::Ignored);
});

it('detects repeatable occurrences and verifies only the corrected record', function () {
    $first = TestModel::create(['name' => 'First', 'paid_at' => now()]);
    $second = TestModel::create(['name' => 'Second', 'paid_at' => now()]);
    TestModel::create(['name' => 'Unpaid']);
    TestModel::create(['name' => 'Already paid', 'paid_at' => now(), 'status' => 'paid']);

    $finding = $this->completeFinding;
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
    $record = $this->completeFinding::found($order);

    expect(DataHealth::resolve($record))->toBeTrue()
        ->and($order->refresh()->status)->toBe('paid')
        ->and($record->refresh()->status)->toBe(RecordStatus::Resolved)
        ->and(DataHealth::resolve($record))->toBeTrue()
        ->and($order->fresh()->status)->toBe('paid');
});

it('preserves valid changes made after detection', function (array $changes) {
    $order = TestModel::create(['name' => 'Changed later', 'paid_at' => now()]);
    $record = $this->completeFinding::found($order);
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
    $record = $this->completeFinding::found($model);

    expect(fn () => DataHealth::$operation($record))
        ->toThrow(LogicException::class, 'Expected an Order model.');

    expect($record->refresh()->status)->toBe(RecordStatus::Active)
        ->and($model->fresh()->status)->toBe('pending');
})->with(['verify', 'resolve']);
