<?php

declare(strict_types=1);

use DataHealth\Enums\FindingUrgency;
use DataHealth\Tests\Fixtures\DataHealth\AutomaticallyResolvedFinding;
use DataHealth\Tests\Fixtures\DataHealth\BasicFinding;
use DataHealth\Tests\Fixtures\DataHealth\UrgentFinding;
use DataHealth\Tests\Fixtures\Models\TestModel;

it('derives its key from the class name', function () {
    expect((new BasicFinding(new TestModel))->key())->toBe('BasicFinding');
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

it('passes context through a finding implementation', function () {
    $context = ['reason' => 'duplicate'];

    expect((new UrgentFinding(new TestModel, $context))->buildContext())->toBe($context);
});

it('reports whether it should be resolved automatically', function () {
    expect((new AutomaticallyResolvedFinding(new TestModel))->isAutomaticallyResolved())->toBeTrue()
        ->and((new BasicFinding(new TestModel))->isAutomaticallyResolved())->toBeFalse();
});
