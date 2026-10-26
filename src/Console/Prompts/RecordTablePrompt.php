<?php

declare(strict_types=1);

namespace DataHealth\Console\Prompts;

use DataHealth\Console\Prompts\Renderers\RecordTableRenderer;
use Laravel\Prompts\Key;
use Laravel\Prompts\MultiSelectPrompt;

class RecordTablePrompt extends MultiSelectPrompt
{
    public string $action = 'exit';

    /** @param array<int, list<string>> $rows */
    public function __construct(
        public readonly array $rows,
        string $label,
        public readonly bool $hasNextPage = false,
        public readonly bool $hasPreviousPage = false,
    ) {
        $options = [];

        foreach ($rows as $id => $row) {
            $options[$id] = (string) $id;
        }

        parent::__construct($label, $options, scroll: min(10, count($rows)));
        $this->clearListeners();
        $this->on('key', function (string $key): void {
            match ($key) {
                Key::UP, Key::UP_ARROW, 'k' => $this->highlightPrevious(count($this->options)),
                Key::DOWN, Key::DOWN_ARROW, 'j' => $this->highlightNext(count($this->options)),
                Key::SPACE => $this->toggleHighlighted(),
                Key::CTRL_A, 'a' => $this->toggleAll(),
                Key::ENTER => $this->chooseAction('inspect'),
                'v' => $this->chooseAction('verify'),
                'r' => $this->chooseAction('resolve'),
                'i' => $this->chooseAction('ignore'),
                's' => $this->chooseAction('solve'),
                'n' => $this->hasNextPage ? $this->chooseAction('next') : null,
                'p' => $this->hasPreviousPage ? $this->chooseAction('previous') : null,
                'f' => $this->chooseAction('refresh'),
                'q' => $this->chooseAction('exit'),
                default => null,
            };
        });
    }

    public function highlightedValue(): int|string|null
    {
        if ($this->highlighted === null) {
            return null;
        }

        return array_keys($this->options)[$this->highlighted] ?? null;
    }

    /** @return list<int|string> */
    public function fallback(): array
    {
        // If raw terminal input fails, let the command switch to its menu flow.
        $this->action = 'menu';

        return [];
    }

    protected function getRenderer(): callable
    {
        return new RecordTableRenderer($this);
    }

    private function chooseAction(string $action): void
    {
        $this->action = $action;

        if ($action === 'inspect' || (in_array($action, ['verify', 'resolve', 'ignore', 'solve'], true) && $this->values === [])) {
            $id = $this->highlightedValue();
            $this->values = $id === null ? [] : [$id];
        }

        $this->submit();
    }
}
