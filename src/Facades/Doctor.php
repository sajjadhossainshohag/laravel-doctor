<?php

namespace SajjadHossain\Doctor\Facades;

use Illuminate\Support\Facades\Facade;

class Doctor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \SajjadHossain\Doctor\CheckRegistry::class;
    }
}
