<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Models\Isolated\HasOneWrong;

use Illuminate\Database\Eloquent\Model;

class HasOneWrongRelated extends Model
{
    protected $table = 'has_one_wrong_related';
    protected $fillable = ['name'];
}