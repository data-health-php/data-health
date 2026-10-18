<?php

declare(strict_types=1);

namespace DataHealth\Console\Concerns;

use InvalidArgumentException;

trait InteractsWithTerminal
{
    protected function interactiveMode(): bool
    {
        if ($this->option('interactive') && ! $this->input->isInteractive()) {
            throw new InvalidArgumentException('--interactive requires an interactive terminal.');
        }

        return $this->input->isInteractive() && ($this->option('interactive') || $this->hasTerminal());
    }

    protected function hasTerminal(): bool
    {
        return defined('STDIN') && defined('STDOUT')
            && stream_isatty(STDIN) && stream_isatty(STDOUT);
    }
}
