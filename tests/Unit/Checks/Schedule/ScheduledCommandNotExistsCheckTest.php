<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Schedule;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Schedule\ScheduledCommandNotExistsCheck;
use SajjadHossain\Doctor\Enums\Severity;

class ScheduledCommandNotExistsCheckTest extends TestCase
{
    /** @test */
    public function it_detects_scheduled_command_class_that_does_not_exist(): void
    {
        $check = (new ScheduledCommandNotExistsCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Console']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsString('NonExistentCommand', $result->locations[0]['issue'] ?? '');
    }
}
