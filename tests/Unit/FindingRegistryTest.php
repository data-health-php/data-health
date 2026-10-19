<?php

declare(strict_types=1);

use DataHealth\FindingRegistry;
use DataHealth\Tests\Fixtures\DataHealth\ActionableFinding;
use DataHealth\Tests\Fixtures\DataHealth\AutomaticallyResolvedFinding;
use DataHealth\Tests\Fixtures\DataHealth\AutomaticallyVerifiedFinding;
use DataHealth\Tests\Fixtures\DataHealth\BasicFinding;
use DataHealth\Tests\Fixtures\DataHealth\Customers\NestedFinding;
use DataHealth\Tests\Fixtures\DataHealth\DelayedAutomaticallyVerifiedFinding;
use DataHealth\Tests\Fixtures\DataHealth\DetectableFinding;
use DataHealth\Tests\Fixtures\DataHealth\KeyedFinding;
use DataHealth\Tests\Fixtures\DataHealth\ScheduledFinding;
use DataHealth\Tests\Fixtures\DataHealth\Support\Helper;
use DataHealth\Tests\Fixtures\DataHealth\UrgentFinding;

beforeEach(function () {
    config()->set('data-health.directories', [
        '../../../../tests/Fixtures/DataHealth' => 'DataHealth\\Tests\\Fixtures\\DataHealth\\',
    ]);
});

it('discovers findings using their default or explicit keys', function () {
    expect((new FindingRegistry)->all()->all())->toBe([
        'ActionableFinding' => ActionableFinding::class,
        'AutomaticallyResolvedFinding' => AutomaticallyResolvedFinding::class,
        'AutomaticallyVerifiedFinding' => AutomaticallyVerifiedFinding::class,
        'BasicFinding' => BasicFinding::class,
        'NestedFinding' => NestedFinding::class,
        'DelayedAutomaticallyVerifiedFinding' => DelayedAutomaticallyVerifiedFinding::class,
        'DetectableFinding' => DetectableFinding::class,
        'duplicate-customer' => KeyedFinding::class,
        'ScheduledFinding' => ScheduledFinding::class,
        'UrgentFinding' => UrgentFinding::class,
    ]);
});

it('ignores discovered classes that are not findings', function () {
    expect((new FindingRegistry)->all())
        ->not->toContain(Helper::class);
});

it('returns only detectable findings', function () {
    expect((new FindingRegistry)->detectable()->all())->toBe([
        'DetectableFinding' => DetectableFinding::class,
        'ScheduledFinding' => ScheduledFinding::class,
    ]);
});

it('returns only detectable findings with a schedule', function () {
    expect((new FindingRegistry)->schedulable()->all())->toBe([
        'ScheduledFinding' => ScheduledFinding::class,
    ]);
});

it('looks up keys and classes in either direction', function () {
    $registry = new FindingRegistry;

    expect($registry->getKey(ScheduledFinding::class))->toBe('ScheduledFinding')
        ->and($registry->getClass('ScheduledFinding'))->toBe(ScheduledFinding::class)
        ->and($registry->getKeyAndClass('ScheduledFinding'))->toBe([
            'ScheduledFinding',
            ScheduledFinding::class,
        ])
        ->and($registry->getKey(KeyedFinding::class))->toBe('duplicate-customer')
        ->and($registry->getClass('duplicate-customer'))->toBe(KeyedFinding::class);
});

it('rejects unknown finding keys and classes', function () {
    expect(fn () => (new FindingRegistry)->getClass('MissingFinding'))
        ->toThrow(RuntimeException::class, 'Finding with key or class MissingFinding not found');
});

it('rejects duplicate finding keys without caching an incomplete registry', function (string $type, string $key, string $first, string $second, bool $separateDirectories) {
    $directory = '../../../../tests/Fixtures/CollidingFindings/'.$type;
    $namespace = 'DataHealth\\Tests\\Fixtures\\CollidingFindings\\'.$type;

    config()->set('data-health.directories', $separateDirectories ? [
        $directory.'/First' => $namespace.'\\First',
        $directory.'/Second' => $namespace.'\\Second',
    ] : [$directory => $namespace]);

    $registry = new FindingRegistry;

    foreach (range(1, 2) as $attempt) {
        expect(fn () => $registry->detectable())->toThrow(
            RuntimeException::class,
            "Duplicate finding key [{$key}] used by [{$namespace}\\First\\{$first}] and [{$namespace}\\Second\\{$second}].",
        );
    }
})->with([
    'default keys' => ['Default', 'DuplicateFinding', 'DuplicateFinding', 'DuplicateFinding'],
    'explicit keys' => ['Explicit', 'shared-key', 'FirstFinding', 'SecondFinding'],
])->with([false, true]);

it('allows the same finding class in overlapping configured directories', function () {
    config()->set('data-health.directories', [
        '../../../../tests/Fixtures/DataHealth' => 'DataHealth\\Tests\\Fixtures\\DataHealth',
        '../../../../tests/Fixtures/DataHealth/Customers' => 'DataHealth\\Tests\\Fixtures\\DataHealth\\Customers',
    ]);

    expect((new FindingRegistry)->all()->all())->toHaveCount(10);
});
