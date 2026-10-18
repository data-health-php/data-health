<?php

declare(strict_types=1);

use DataHealth\Console\Prompts\RecordTablePrompt;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

beforeEach(function () {
    $this->rows = [
        12 => ['12', 'DuplicateCustomer', 'Customer:3', 'active', 'normal', 'verify, resolve'],
        18 => ['18', 'MissingInvoice', 'Order:7', 'active', 'soon', 'verify'],
    ];
});

it('acts on the highlighted record when no rows are selected', function (string $key, string $action) {
    Prompt::fake([Key::DOWN, $key]);
    $prompt = new RecordTablePrompt($this->rows, 'Page 1 of 1');

    expect($prompt->prompt())->toBe([18])
        ->and($prompt->action)->toBe($action);
})->with([['r', 'resolve'], ['v', 'verify'], ['i', 'ignore'], ['s', 'solve'], [Key::ENTER, 'inspect']]);

it('acts on checked rows rather than the highlighted row', function () {
    Prompt::fake([Key::SPACE, Key::DOWN, 'r']);
    $prompt = new RecordTablePrompt($this->rows, 'Page 1 of 1');

    expect($prompt->prompt())->toBe([12])
        ->and($prompt->action)->toBe('resolve');
});

it('can select multiple rows and toggle all rows', function (array $keys, array $ids) {
    Prompt::fake($keys);
    $prompt = new RecordTablePrompt($this->rows, 'Page 1 of 1');

    expect($prompt->prompt())->toBe($ids);
})->with([
    [[Key::SPACE, Key::DOWN, Key::SPACE, 'v'], [12, 18]],
    [['a', 'r'], [12, 18]],
    [['a', 'a', Key::DOWN, 'r'], [18]],
    [[Key::SPACE, Key::DOWN, 'i'], [12]],
    [[Key::SPACE, Key::DOWN, 's'], [12]],
]);

it('inspects the highlighted row even when other rows are checked', function () {
    Prompt::fake([Key::SPACE, Key::DOWN, Key::ENTER]);
    $prompt = new RecordTablePrompt($this->rows, 'Page 1 of 1');

    expect($prompt->prompt())->toBe([18])
        ->and($prompt->action)->toBe('inspect');
});

it('supports paging refresh and quit shortcuts', function (string $key, string $action) {
    Prompt::fake([$key]);
    $prompt = new RecordTablePrompt($this->rows, 'Page 2 of 3', hasNextPage: true, hasPreviousPage: true);
    $prompt->prompt();

    expect($prompt->action)->toBe($action);
})->with([['n', 'next'], ['p', 'previous'], ['f', 'refresh'], ['q', 'exit']]);

it('ignores page shortcuts when the page does not exist', function () {
    Prompt::fake(['n', 'p', 'q']);
    $prompt = new RecordTablePrompt($this->rows, 'Page 1 of 1');
    $prompt->prompt();

    expect($prompt->action)->toBe('exit');
});

it('renders column headings selection markers and keyboard help', function () {
    Prompt::fake([Key::SPACE, 'q']);
    (new RecordTablePrompt($this->rows, 'Page 1 of 1'))->prompt();

    Prompt::assertStrippedOutputContains('Finding');
    Prompt::assertStrippedOutputContains('MissingInvoice');
    Prompt::assertStrippedOutputContains('[x]');
    Prompt::assertStrippedOutputContains('r resolve');
    Prompt::assertStrippedOutputContains('i ignore');
    Prompt::assertStrippedOutputContains('s mark solved');
});

it('fits long values within a narrow terminal and removes terminal control characters', function () {
    Prompt::fake(['q']);
    Prompt::terminal()->shouldReceive('cols')->andReturn(40);
    $this->rows[12][1] = str_repeat('長い名前', 20)."\e[2J\nInjected";
    (new RecordTablePrompt($this->rows, 'Page 1 of 1'))->prompt();

    foreach (explode(PHP_EOL, Prompt::strippedContent()) as $line) {
        expect(mb_strwidth($line))->toBeLessThanOrEqual(40);
    }
});

it('scrolls the table to keep the highlighted record visible', function () {
    $rows = [];

    foreach (range(1, 15) as $id) {
        $rows[$id] = [(string) $id, 'Finding'.$id, 'Model:'.$id, 'active', 'normal', 'resolve'];
    }

    Prompt::fake([...array_fill(0, 14, Key::DOWN), 'r']);
    $prompt = new RecordTablePrompt($rows, 'Page 1 of 1');

    expect($prompt->prompt())->toBe([15]);
    Prompt::assertStrippedOutputContains('Finding15');
});
