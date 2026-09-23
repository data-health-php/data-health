<?php

declare(strict_types=1);

use DataHealth\DataHealthCheckCursor;
use DataHealth\Models\DataHealthCursor;
use DataHealth\Tests\Fixtures\DataHealth\BasicFinding;
use DataHealth\Tests\Fixtures\DataHealth\UrgentFinding;
use DataHealth\Tests\Fixtures\Models\TestModel;

beforeEach(function () {
    $this->migrateDataHealthDatabase();
    $this->cursor = new DataHealthCheckCursor;
});

it('returns models in ordered chunks and wraps after the final chunk', function () {
    $models = collect(range(1, 5))
        ->map(fn (int $number) => TestModel::create(['name' => "Model {$number}"]));

    $first = $this->cursor->next(TestModel::query(), BasicFinding::class, 2);
    $second = $this->cursor->next(TestModel::query(), BasicFinding::class, 2);
    $third = $this->cursor->next(TestModel::query(), BasicFinding::class, 2);
    $wrapped = $this->cursor->next(TestModel::query(), BasicFinding::class, 2);

    expect($first->modelKeys())->toBe($models->take(2)->pluck('id')->all())
        ->and($second->modelKeys())->toBe($models->slice(2, 2)->pluck('id')->all())
        ->and($third->modelKeys())->toBe($models->slice(4)->pluck('id')->all())
        ->and($wrapped->modelKeys())->toBe($models->take(2)->pluck('id')->all());
});

it('resets an exhausted cursor and returns an empty collection', function () {
    DataHealthCursor::create([
        'key' => BasicFinding::class,
        'last_id' => 999,
    ]);

    $result = $this->cursor->next(TestModel::query(), BasicFinding::class, 10);

    expect($result)->toBeEmpty()
        ->and(DataHealthCursor::where('key', BasicFinding::class)->value('last_id'))->toBe(0);
});

it('tracks cursors independently per finding', function () {
    TestModel::create(['name' => 'First']);
    TestModel::create(['name' => 'Second']);

    $this->cursor->next(TestModel::query(), BasicFinding::class, 1);
    $this->cursor->next(TestModel::query(), UrgentFinding::class, 2);

    expect(DataHealthCursor::where('key', BasicFinding::class)->value('last_id'))->toBe(1)
        ->and(DataHealthCursor::where('key', UrgentFinding::class)->value('last_id'))->toBe(0);
});
