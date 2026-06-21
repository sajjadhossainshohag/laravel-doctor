<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Cache;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Cache\CacheDriverNotRunningCheck;
use SajjadHossain\Doctor\Enums\Severity;

class CacheDriverNotRunningCheckTest extends TestCase
{
    /** @test */
    public function it_detects_unreachable_cache_driver(): void
    {
        config(['cache.default' => 'redis']);
        config(['database.redis.default.host' => '127.0.0.1']);
        config(['database.redis.default.port' => 9999]);

        $check = new CacheDriverNotRunningCheck();
        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
    }

    /** @test */
    public function it_passes_when_array_cache_driver_is_configured(): void
    {
        config(['cache.default' => 'array']);

        $result = (new CacheDriverNotRunningCheck())->run();

        $this->assertCheckPassed($result);
    }
}
