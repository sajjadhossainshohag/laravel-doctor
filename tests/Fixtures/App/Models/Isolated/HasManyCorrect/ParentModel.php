<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Isolated\HasManyCorrect;

use Illuminate\Database\Eloquent\Model;

/**
 * Convention FK for hasMany(): FK is the 2nd arg, after the related
 * class. hasManyCorrectParent uses the conventional
 * 'has_many_correct_parent_id' (parent model + '_id').
 *
 * hasManyCorrectLocal has a custom LOCAL key (3rd arg) which must NOT
 * be mistakenly validated as a foreign key by the check.
 */
class HasManyCorrectParent extends Model
{
    protected $table = 'has_many_correct_parents';
    protected $fillable = ['name'];

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HasManyRelated::class, 'has_many_correct_parent_id', 'local_pk_col');
    }
}