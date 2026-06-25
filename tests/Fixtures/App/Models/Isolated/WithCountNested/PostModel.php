<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Isolated\WithCountNested;

use Illuminate\Database\Eloquent\Model;

class WithCountNestedPost extends Model
{
    protected $table = 'with_count_nested_posts';
    protected $fillable = ['title'];

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WithCountNestedComment::class);
    }
}