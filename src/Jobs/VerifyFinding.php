<?php

declare(strict_types=1);

namespace DataHealth\Jobs;

use DataHealth\DataHealthManager;
use DataHealth\Enums\RecordStatus;
use DataHealth\Models\FindingRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class VerifyFinding implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $recordId) {}

    public function handle(DataHealthManager $manager): void
    {
        $record = FindingRecord::query()->find($this->recordId);

        if ($record === null || $record->status !== RecordStatus::Active) {
            return;
        }

        $manager->verify($record);
    }
}
