<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Isolated\ValueVsFirst;

class GuardedFirstCall
{
    public function load()
    {
        $query = \App\Models\User::query();

        if ($query->count() > 0) {
            $name = $query->first()->name;
            return $name;
        }

        return null;
    }
}
