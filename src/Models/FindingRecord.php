<?php

declare(strict_types=1);

namespace DataHealth\Models;

use DataHealth\Enums\FindingUrgency;
use DataHealth\Enums\RecordStatus;
use DataHealth\Facades\DataHealth;
use DataHealth\Finding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property RecordStatus $status
 * @property string $key
 * @property string $model_type
 * @property int $model_id
 * @property array<array-key, mixed> $context
 * @property string $context_hash
 * @property string|null $assignee_type
 * @property int|null $assignee_id
 * @property string|null $worklist
 * @property FindingUrgency $urgency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $model
 */
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
        'context_hash',
        'worklist',
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
