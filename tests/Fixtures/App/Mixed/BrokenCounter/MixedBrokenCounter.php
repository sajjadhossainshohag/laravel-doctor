<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Mixed\BrokenCounter;

/**
 * Mixed-project case: a v2-style component that exists in a
 * non-standard mixed path. Verifies the check picks up arbitrary
 * paths via withPaths().
 */
class MixedBrokenCounter
{
    public string $view = 'livewire.mixed-broken-counter';

    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }
}