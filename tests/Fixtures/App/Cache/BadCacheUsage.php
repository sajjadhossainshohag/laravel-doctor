<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Cache;

use Illuminate\Support\Facades\Cache;

class BadCacheUsage
{
    public function getData()
    {
        return Cache::remember('key', 60, function () {
            return fn () => 'value';
        });
    }

    public function getTags()
    {
        return Cache::tags(['tag1', 'tag2'])->get('key');
    }
}
