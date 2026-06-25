<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Good;

use Illuminate\Database\Eloquent\Model;

/**
 * Tests that `$guarded = '*'` (Laravel's legacy default-string form) is
 * recognized as an explicit declaration.
 */
class StringGuardedModel extends Model
{
    protected $table = 'string_guarded_models';
    protected $guarded = '*';
}