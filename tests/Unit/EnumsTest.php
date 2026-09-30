<?php

declare(strict_types=1);

use DataHealth\Enums\FindingUrgency;
use DataHealth\Enums\RecordStatus;

it('exposes stable urgency values', function () {
    expect(array_column(FindingUrgency::cases(), 'value'))->toBe([
        'immediate',
        'soon',
        'normal',
        'deferred',
    ]);
});

it('exposes status values and labels', function () {
    expect(array_column(RecordStatus::cases(), 'value'))->toBe([
        'active',
        'ignored',
        'resolved',
    ])->and(RecordStatus::Active->getLabel())->toBe('Active');
});
