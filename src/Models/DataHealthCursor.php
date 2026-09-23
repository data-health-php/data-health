<?php

declare(strict_types=1);

namespace DataHealth\Models;

use Illuminate\Database\Eloquent\Model;

class DataHealthCursor extends Model
{
    protected $fillable = [
        'key',
        'last_id',
    ];
}
