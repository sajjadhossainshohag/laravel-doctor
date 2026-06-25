<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Good;

use Illuminate\Database\Eloquent\Model;

/**
 * Parent class — declares $fillable so any child inheriting from it
 * should be considered "safe" by the check, even if the child itself
 * does not redeclare $fillable.
 */
class FillableBaseModel extends Model
{
    protected $table = 'fillable_base_models';
    protected $fillable = ['id', 'created_at'];
}

class InheritedFillableModel extends FillableBaseModel
{
    protected $table = 'inherited_fillable_models';
    // Intentionally does NOT redeclare $fillable — but parent has it.
}