<?php

declare(strict_types=1);

use DataHealth\ContextHasher;

it('creates a sha256 hash from json context', function () {
    $hash = (new ContextHasher)->hash(['reason' => 'duplicate']);

    expect($hash)->toBe(hash('sha256', '{"reason":"duplicate"}'))
        ->and(strlen($hash))->toBe(64);
});

it('recursively ignores associative key order', function () {
    $hasher = new ContextHasher;

    $first = $hasher->hash([
        'reason' => 'duplicate',
        'meta' => [
            'source' => 'import',
            'attempt' => 1,
        ],
    ]);
    $second = $hasher->hash([
        'meta' => [
            'attempt' => 1,
            'source' => 'import',
        ],
        'reason' => 'duplicate',
    ]);

    expect($second)->toBe($first);
});

it('preserves list order', function () {
    $hasher = new ContextHasher;

    expect($hasher->hash(['values' => ['first', 'second']]))
        ->not->toBe($hasher->hash(['values' => ['second', 'first']]));
});

it('distinguishes additional values', function () {
    $hasher = new ContextHasher;

    expect($hasher->hash(['reason' => 'duplicate']))
        ->not->toBe($hasher->hash([
            'reason' => 'duplicate',
            'source' => 'import',
        ]));
});

it('rejects values that cannot be encoded as json', function () {
    $resource = fopen('php://memory', 'r');

    try {
        expect(fn () => (new ContextHasher)->hash(['resource' => $resource]))
            ->toThrow(JsonException::class);
    } finally {
        fclose($resource);
    }
});
