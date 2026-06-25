<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Cache\Isolated;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Bug regression for Cache/RememberReturnsClosureCheck:
 *
 * Cache::remember() callback that calls Http::get('https://...') — the
 * callback returns a non-Closure value. With the OLD naive
 * `!//.*!` regex, the `//` inside the URL was incorrectly stripped as
 * a line comment, corrupting the source and breaking the parser.
 *
 * Must NOT be flagged.
 */
class HttpRemember
{
    public function getData()
    {
        return Cache::remember('http-key', 60, function () {
            return Http::get('https://example.com/api/data')->json();
        });
    }
}