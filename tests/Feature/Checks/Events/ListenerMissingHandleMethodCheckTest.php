<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Events;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Events\ListenerMissingHandleMethodCheck;
use SajjadHossain\Doctor\Enums\Severity;

class ListenerMissingHandleMethodCheckTest extends TestCase
{
    /** @test */
    public function it_detects_listener_missing_dispatch_method(): void
    {
        $result = (new ListenerMissingHandleMethodCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Listeners'])
            ->run();

        $this->assertCheckFailed($result, Severity::Warning);
    }
}
