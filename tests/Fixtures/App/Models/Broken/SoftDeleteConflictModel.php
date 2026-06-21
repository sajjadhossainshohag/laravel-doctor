<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Broken;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftDeleteConflictModel extends Model
{
    use SoftDeletes;

    protected $table = 'soft_delete_conflict_models';
    protected $fillable = ['name'];

    public function scopeActive($query)
    {
        return $query->where('deleted_at', null);
    }
}
