<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Isolated\HasOneCorrect;

use Illuminate\Database\Eloquent\Model;

/**
 * hasOne() with the conventional FK (parent_snake_case_id) and an
 * explicit local key — must NOT be flagged.
 */
class HasOneCorrectParent extends Model
{
    protected $table = 'has_one_correct_parents';
    protected $fillable = ['name'];

    public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(HasOneRelated::class, 'has_one_correct_parent_id', 'local_id_col');
    }
}