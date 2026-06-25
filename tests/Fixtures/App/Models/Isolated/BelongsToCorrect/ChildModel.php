<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Isolated\BelongsToCorrect;

use Illuminate\Database\Eloquent\Model;

/**
 * belongsTo() with conventional FK (related_snake_case_id) — must pass.
 */
class BelongsToCorrectChild extends Model
{
    protected $table = 'belongs_to_correct_children';
    protected $fillable = ['name'];

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BelongsToCorrectParent::class, 'belongs_to_correct_parent_id');
    }
}