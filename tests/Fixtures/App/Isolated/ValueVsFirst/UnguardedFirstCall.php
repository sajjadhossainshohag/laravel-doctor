<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Isolated\ValueVsFirst;

class UnguardedFirstCall
{
    public function load()
    {
        $user = \App\Models\User::query()->first()->name;
        return $user;
    }
}
