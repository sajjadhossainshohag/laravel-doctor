<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Cache\Isolated;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Double-quoted URL: "http://..." — same test, different quote style.
 */
class DoubleQuotedUrl
{
    public function getData()
    {
        return Cache::remember("dq-key", 60, function () {
            return Http::get("http://example.com/api")->json();
        });
    }
}