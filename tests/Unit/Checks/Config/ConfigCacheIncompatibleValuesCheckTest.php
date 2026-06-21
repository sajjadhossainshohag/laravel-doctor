<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Config;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Config\ConfigCacheIncompatibleValuesCheck;
use SajjadHossain\Doctor\Enums\Severity;

class ConfigCacheIncompatibleValuesCheckTest extends TestCase
{
    /** @test */
    public function it_detects_closure_inside_config_file(): void
    {
        $check = (new ConfigCacheIncompatibleValuesCheck())
            ->withConfigPaths([__DIR__.'/../../../Fixtures/Config']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning, 'Closure');
    }

    /** @test */
    public function it_passes_when_config_file_has_no_closures(): void
    {
        $check = (new ConfigCacheIncompatibleValuesCheck())
            ->withConfigPaths([__DIR__.'/../../../Fixtures/Lang']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }
}
