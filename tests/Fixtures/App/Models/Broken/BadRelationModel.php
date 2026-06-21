<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Broken;

use Illuminate\Database\Eloquent\Model;

class BadRelationModel extends Model
{
    protected $table = 'bad_relation_models';
    protected $fillable = ['name'];

    public function ghost(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\SajjadHossain\Doctor\Tests\Fixtures\App\Models\Broken\NonExistentRelated::class);
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(
            \SajjadHossain\Doctor\Tests\Fixtures\App\Models\Good\WellDefinedModel::class,
            'completely_wrong_fk',
        );
    }
}
