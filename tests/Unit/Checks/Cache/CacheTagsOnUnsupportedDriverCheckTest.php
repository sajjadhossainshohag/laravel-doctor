<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Cache;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Cache\CacheTagsOnUnsupportedDriverCheck;
use SajjadHossain\Doctor\Enums\Severity;

class CacheTagsOnUnsupportedDriverCheckTest extends TestCase
{
    /** @test */
    public function it_detects_cache_tags_usage_with_file_driver(): void
    {
        config(['cache.default' => 'file']);

        $check = (new CacheTagsOnUnsupportedDriverCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Error, 'tags');
    }

    /** @test */
    public function it_passes_when_redis_driver_is_used_with_tags(): void
    {
        config(['cache.default' => 'redis']);

        $result = (new CacheTagsOnUnsupportedDriverCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App'])
            ->run();

        $this->assertCheckPassed($result);
    }
}
