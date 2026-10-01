<?php

declare(strict_types=1);

use DataHealth\Enums\FindingUrgency;
use DataHealth\Tests\Fixtures\DataHealth\ActionableFinding;
use DataHealth\Tests\Fixtures\DataHealth\AutomaticallyResolvedFinding;
use DataHealth\Tests\Fixtures\DataHealth\BasicFinding;
use DataHealth\Tests\Fixtures\DataHealth\DetectableFinding;
use DataHealth\Tests\Fixtures\DataHealth\KeyedFinding;
use DataHealth\Tests\Fixtures\DataHealth\UrgentFinding;
use DataHealth\Tests\Fixtures\Models\TestModel;

it('derives its key from the class name', function () {
    expect(BasicFinding::key())->toBe('BasicFinding');
});

it('uses the key attribute when present', function () {
    expect(KeyedFinding::key())->toBe('duplicate-customer');
});

it('uses empty context by default', function () {
    $finding = new BasicFinding(new TestModel);

    expect($finding->context)->toBe([])
        ->and($finding->buildContext())->toBe([]);
});

it('reads urgency from the urgency attribute', function () {
    expect(UrgentFinding::getUrgency())->toBe(FindingUrgency::SOON)
        ->and(BasicFinding::getUrgency())->toBeNull();
});

it('reads worklist from the worklist attribute', function () {
    expect(UrgentFinding::getWorklist())->toBe('data-quality')
        ->and(BasicFinding::getWorklist())->toBeNull();
});

it('reads descriptions from finding classes and action methods', function () {
    expect(ActionableFinding::getDescription())->toBe('A finding that can be verified and resolved.')
        ->and(ActionableFinding::getMethodDescription('verify'))->toBe('Checks whether the finding still applies.')
        ->and(ActionableFinding::getMethodDescription('resolve'))->toBe('Corrects the problem represented by the finding.')
        ->and(DetectableFinding::getMethodDescription('detect'))->toBe('Searches for detectable problems.');
});

it('returns null when a description or method is missing', function () {
    expect(BasicFinding::getDescription())->toBeNull()
        ->and(BasicFinding::getMethodDescription('verify'))->toBeNull();
});

it('passes context through a finding implementation', function () {
    $context = ['reason' => 'duplicate'];

    expect((new UrgentFinding(new TestModel, $context))->buildContext())->toBe($context);
});

it('reports whether it should be resolved automatically', function () {
    expect((new AutomaticallyResolvedFinding(new TestModel))->isAutomaticallyResolved())->toBeTrue()
        ->and((new BasicFinding(new TestModel))->isAutomaticallyResolved())->toBeFalse();
});
