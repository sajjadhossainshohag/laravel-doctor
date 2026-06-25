<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Isolated\WithCountNested;

use Illuminate\Database\Eloquent\Model;

class WithCountNestedComment extends Model
{
    protected $table = 'with_count_nested_comments';
    protected $fillable = ['name'];
}