<?php

declare(strict_types=1);

namespace DataHealth\Concerns;

use DataHealth\Models\FindingRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasFindingRecords
{
    public static function bootHasFindingRecords(): void
    {
        static::deleted(function (Model $model): void {
            if (config('data-health.auto_delete.enabled')) {
                return;
            }

            FindingRecord::query()
                ->where('model_type', $model->getMorphClass())
                ->where('model_id', $model->getKey())
                ->delete();
        });
    }

    /**
     * @return MorphMany<FindingRecord, $this>
     */
    public function findingRecords(): MorphMany
    {
        return $this->morphMany(FindingRecord::class, 'model');
    }
}
