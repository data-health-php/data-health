<?php

declare(strict_types=1);

namespace DataHealth\Console\Commands;

use Illuminate\Console\Command;

class DataHealthCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'data-health:placeholder';

    /**
     * The command description.
     */
    protected $description = 'Placeholder Artisan command shipped by the package data-health.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('DataHealth placeholder command executed.');

        return self::SUCCESS;
    }
}
