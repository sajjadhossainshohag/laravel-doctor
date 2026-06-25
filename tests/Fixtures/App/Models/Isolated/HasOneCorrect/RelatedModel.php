<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Isolated\HasOneCorrect;

use Illuminate\Database\Eloquent\Model;

class HasOneRelated extends Model
{
    protected $table = 'has_one_related';
    protected $fillable = ['has_one_correct_parent_id'];
}