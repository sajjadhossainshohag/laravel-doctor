<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Cache\Isolated;

use Illuminate\Support\Facades\Cache;

/**
 * The Cache::remember() call here is COMMENTED OUT. The check must
 * not flag commented-out code as live code. (This was previously
 * working in the prior round but is included to guard against
 * regression in the new string-aware stripper.)
 */
class CommentedOut
{
    public function getData()
    {
        // Cache::remember('key', 60, function () { return fn () => 'x'; });
        /*
        Cache::remember('key', 60, function () {
            return function () { return 'closure'; };
        });
        */
        return null;
    }
}