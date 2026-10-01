<?php

declare(strict_types=1);

namespace DataHealth\Listeners;

use DataHealth\Models\FindingRecord;
use Illuminate\Database\Eloquent\Model;

class DeleteFindingRecordsForDeletedModel
{
    /** @param array<int, mixed> $payload */
    public function handle(string $event, array $payload): void
    {
        $model = $payload[0] ?? null;

        if (! $model instanceof Model || $model instanceof FindingRecord) {
            return;
        }

        FindingRecord::query()
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->delete();
    }
}
