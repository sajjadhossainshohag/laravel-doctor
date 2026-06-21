<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Policies;

class BrokenPolicy
{
    public function view(): bool
    {
        return false;
    }
}
