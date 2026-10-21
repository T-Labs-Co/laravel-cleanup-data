<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestModel extends Model
{
    use SoftDeletes;

    protected $table = 'test_models';

    protected $guarded = [];
}
