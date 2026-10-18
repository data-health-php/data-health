<?php

declare(strict_types=1);

namespace DataHealth\Console\Commands;

use DataHealth\DataHealthManager;
use DataHealth\Models\FindingRecord;
use Illuminate\Console\Command;
use Throwable;

class VerifyCommand extends Command
{
    /** @var string */
    protected $signature = 'verify {id : The finding record ID}';

    /** @var string */
    protected $description = 'Verify a Data Health finding record';

    /** @var list<string> */
    protected $aliases = ['health:verify'];

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

            if (! $manager->verify($record)) {
                $this->error("Finding record {$id} could not be verified.");

                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Finding record {$id} verified.");

        return self::SUCCESS;
    }
}
