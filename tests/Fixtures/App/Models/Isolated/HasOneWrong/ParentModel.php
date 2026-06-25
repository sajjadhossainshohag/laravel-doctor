<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Isolated\HasOneWrong;

use Illuminate\Database\Eloquent\Model;

/**
 * hasOne() with a non-conventional FK. The third arg is the local key
 * (not the FK). With the OLD bug, the check would have validated the
 * local key 'some_local_id' (which is snake_case and _id-suffixed)
 * instead of the actual FK 'unrelated_id', missing this bug.
 */
class HasOneWrongParent extends Model
{
    protected $table = 'has_one_wrong_parents';
    protected $fillable = ['name'];

    public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(HasOneWrongRelated::class, 'unrelated_id', 'some_local_id');
    }
}