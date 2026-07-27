<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Schema;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $table = 'profiles';

    protected $fillable = ['name', 'email', 'bio'];

    protected $casts = [
        'bio' => 'string',
    ];
}
