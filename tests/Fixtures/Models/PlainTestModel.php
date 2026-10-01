<?php

declare(strict_types=1);

namespace DataHealth\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class PlainTestModel extends Model
{
    protected $table = 'data_health_test_models';

    protected $guarded = [];
}
