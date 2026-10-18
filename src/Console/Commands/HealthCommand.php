<?php

declare(strict_types=1);

namespace DataHealth\Console\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\select;
use function Termwind\parse;

class HealthCommand extends Command
{
    /** @var string */
    protected $signature = 'health';

    /** @var string */
    protected $description = 'Open the interactive Data Health command suite';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Use health:detect or health:records for non-interactive runs.');

            return self::FAILURE;
        }

        $this->line(parse('<div class="my-1"><span class="bg-cyan-600 px-1 font-bold text-white">Data Health</span> <span class="text-gray-500">Find, verify, resolve</span></div>'));
        $failed = false;

        while (true) {
            $action = select('What would you like to do?', [
                'detect' => 'Detect findings',
                'records' => 'Browse, verify, and resolve records',
                'exit' => 'Exit',
            ], default: 'exit');

            if ($action === 'exit') {
                return $failed ? self::FAILURE : self::SUCCESS;
            }

            $result = $this->call($action === 'detect' ? 'health:detect' : 'health:records', ['--interactive' => true]);
            $failed = $result !== self::SUCCESS || $failed;
        }
    }
}
