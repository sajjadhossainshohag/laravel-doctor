<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Good;

use Illuminate\Database\Eloquent\Model;

/**
 * $guarded = [] is an explicit declaration of "mass-assign everything"
 * — the developer was deliberate. The check should not flag this.
 */
class EmptyGuardedModel extends Model
{
    protected $table = 'empty_guarded_models';
    protected $guarded = [];
}