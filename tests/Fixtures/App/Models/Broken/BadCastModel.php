<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Broken;

use Illuminate\Database\Eloquent\Model;

class BadCastModel extends Model
{
    protected $table = 'bad_cast_models';
    protected $fillable = ['name'];

    protected $casts = [
        'non_existent_column' => 'array',
        'status'              => \NonExistentEnum::class,
    ];
}
