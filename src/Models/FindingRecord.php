<?php

declare(strict_types=1);

namespace DataHealth\Models;

use DataHealth\Enums\FindingUrgency;
use DataHealth\Enums\RecordStatus;
use DataHealth\Facades\DataHealth;
use DataHealth\Finding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FindingRecord extends Model
{
    protected $table = 'data_health_findings';

    protected $fillable = [
        'status',
        'key',
        'model_type',
        'model_id',
        'role',
        'context',
        'updated_at',
        'urgency',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'context' => 'array',
            'urgency' => FindingUrgency::class,
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function getFinding(): Finding
    {
        return DataHealth::getFindingForRecord($this);
    }
}
