<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Cache;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Cache\RememberReturnsClosureCheck;
use SajjadHossain\Doctor\Enums\Severity;

class RememberReturnsClosureCheckTest extends TestCase
{
    /** @test */
    public function it_detects_closure_returned_inside_remember_callback(): void
    {
        // Use a serializing cache driver so the check actually scans
        // for Closure-returning callbacks. (The default `array` driver
        // is non-serializing and the check correctly passes there.)
        config(['cache.default' => 'file']);

        $check = (new RememberReturnsClosureCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning, 'Closure');
    }
}
