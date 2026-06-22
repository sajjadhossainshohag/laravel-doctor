<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Config;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Config\EarlyConfigAccessCheck;
use SajjadHossain\Doctor\Enums\Severity;

class EarlyConfigAccessCheckTest extends TestCase
{
    /** @test */
    public function it_detects_config_called_in_service_provider_register(): void
    {
        $check = (new EarlyConfigAccessCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Providers']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning, 'register()');
    }
}
