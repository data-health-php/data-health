<?php

declare(strict_types=1);

use DataHealth\FindingRegistry;
use DataHealth\Tests\Fixtures\DataHealth\ActionableFinding;
use DataHealth\Tests\Fixtures\DataHealth\AutomaticallyResolvedFinding;
use DataHealth\Tests\Fixtures\DataHealth\BasicFinding;
use DataHealth\Tests\Fixtures\DataHealth\Customers\NestedFinding;
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
        'BasicFinding' => BasicFinding::class,
        'NestedFinding' => NestedFinding::class,
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
        ->toThrow(RuntimeException::class, 'Fail with key or class MissingFinding not found');
});
