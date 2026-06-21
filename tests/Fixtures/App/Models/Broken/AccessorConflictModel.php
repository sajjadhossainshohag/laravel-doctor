<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Broken;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class AccessorConflictModel extends Model
{
    protected $table = 'accessor_conflict_models';
    protected $fillable = ['name'];

    protected $casts = [
        'name' => 'string',
    ];

    public function getNameAttribute($value): string
    {
        return strtoupper($value);
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => strtolower($value),
        );
    }
}
