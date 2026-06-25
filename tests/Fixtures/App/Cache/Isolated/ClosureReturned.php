<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Cache\Isolated;

use Illuminate\Support\Facades\Cache;

/**
 * Real Closure return — should still be flagged.
 */
class ClosureReturned
{
    public function getData()
    {
        return Cache::remember('closure-key', 60, function () {
            return function () { return 'closure'; };
        });
    }
}