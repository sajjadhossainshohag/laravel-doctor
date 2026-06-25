<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Isolated\HasManyWrong;

use Illuminate\Database\Eloquent\Model;

/**
 * Intentionally uses a non-conventional FK ('naming_violation_id').
 * The check SHOULD flag this — the FK doesn't follow the snake_case
 * convention based on the parent model name
 * (which would be 'has_many_wrong_parent_id').
 *
 * Important: the second arg here ('naming_violation_id') is the FK,
 * the third arg is the local key. With the OLD bug, the check would
 * have validated the THIRD arg instead — wrongly ignoring the actual FK.
 */
class HasManyWrongParent extends Model
{
    protected $table = 'has_many_wrong_parents';
    protected $fillable = ['name'];

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HasManyWrongRelated::class, 'naming_violation_id', 'local_pk');
    }
}