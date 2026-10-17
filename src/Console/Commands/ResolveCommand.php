<?php

declare(strict_types=1);

namespace DataHealth\Console\Commands;

use DataHealth\DataHealthManager;
use DataHealth\Models\FindingRecord;
use Illuminate\Console\Command;
use Throwable;

class ResolveCommand extends Command
{
    /** @var string */
    protected $signature = 'resolve {id : The finding record ID}';

    /** @var string */
    protected $description = 'Resolve a Data Health finding record';

    public function handle(DataHealthManager $manager): int
    {
        /** @var string $id */
        $id = $this->argument('id');

        try {
            $record = FindingRecord::query()->find($id);

            if ($record === null) {
                $this->error("Finding record {$id} was not found.");

                return self::FAILURE;
            }

            if (! $manager->resolve($record)) {
                $this->error("Finding record {$id} could not be resolved.");

                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Finding record {$id} resolved.");

        return self::SUCCESS;
    }
}
