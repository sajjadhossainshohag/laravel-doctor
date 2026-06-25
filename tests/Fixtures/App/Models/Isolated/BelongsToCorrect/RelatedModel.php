<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Isolated\BelongsToCorrect;

use Illuminate\Database\Eloquent\Model;

class BelongsToCorrectParent extends Model
{
    protected $table = 'belongs_to_correct_parents';
    protected $fillable = ['name'];
}