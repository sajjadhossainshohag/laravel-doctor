<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Good;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WellDefinedModel extends Model
{
    use SoftDeletes;

    protected $table = 'well_defined_models';

    protected $fillable = ['name', 'status', 'meta'];

    protected $casts = [
        'meta'       => 'array',
        'status'     => 'string',
        'deleted_at' => 'datetime',
    ];

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
