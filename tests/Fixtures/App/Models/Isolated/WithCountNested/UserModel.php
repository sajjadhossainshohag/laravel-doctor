<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Isolated\WithCountNested;

use Illuminate\Database\Eloquent\Model;

/**
 * The PARENT model — declares a 'posts' relationship. Calling
 * ->withCount('posts.comments') is valid because Laravel will resolve
 * the .comments part through Post::comments() at runtime.
 *
 * This fixture MUST NOT be flagged by the check.
 */
class WithCountNestedUser extends Model
{
    protected $table = 'with_count_nested_users';
    protected $fillable = ['name'];

    public function posts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WithCountNestedPost::class);
    }
}