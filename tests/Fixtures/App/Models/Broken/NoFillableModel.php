<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Broken;

use Illuminate\Database\Eloquent\Model;

class NoFillableModel extends Model
{
    protected $table = 'no_fillable_models';
}
