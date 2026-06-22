<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Container;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Container\SingletonAfterFirstResolveCheck;
use SajjadHossain\Doctor\Enums\Severity;

class SingletonAfterFirstResolveCheckTest extends TestCase
{
    /** @test */
    public function it_detects_singleton_registered_in_boot_method(): void
    {
        $check = (new SingletonAfterFirstResolveCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Providers']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning, 'singleton');
    }

    /** @test */
    public function it_passes_when_no_singletons_in_boot(): void
    {
        $check = (new SingletonAfterFirstResolveCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/Config']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }
}
