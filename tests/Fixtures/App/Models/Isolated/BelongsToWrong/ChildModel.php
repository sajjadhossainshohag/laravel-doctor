<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Isolated\BelongsToWrong;

use Illuminate\Database\Eloquent\Model;

/**
 * belongsTo() with non-conventional FK ('wrong_association_id') — must
 * be flagged. The convention for belongsTo is
 * snake_case(parent_model_short_name) + '_id', which would be
 * 'belongs_to_wrong_parent_id'.
 */
class BelongsToWrongChild extends Model
{
    protected $table = 'belongs_to_wrong_children';
    protected $fillable = ['name'];

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BelongsToWrongParent::class, 'wrong_association_id');
    }
}