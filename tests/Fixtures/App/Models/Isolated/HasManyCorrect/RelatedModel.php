<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Isolated\HasManyCorrect;

use Illuminate\Database\Eloquent\Model;

class HasManyRelated extends Model
{
    protected $table = 'has_many_related';
    protected $fillable = ['has_many_correct_parent_id'];
}