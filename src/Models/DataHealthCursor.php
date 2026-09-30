<?php

declare(strict_types=1);

namespace DataHealth\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int|null $last_id
 */
class DataHealthCursor extends Model
{
    protected $fillable = [
        'key',
        'last_id',
    ];
}
