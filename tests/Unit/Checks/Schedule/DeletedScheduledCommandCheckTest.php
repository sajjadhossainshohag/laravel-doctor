<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Schedule;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Schedule\DeletedScheduledCommandCheck;
use SajjadHossain\Doctor\Enums\Severity;

class DeletedScheduledCommandCheckTest extends TestCase
{
    /** @test */
    public function it_detects_scheduled_command_name_not_in_artisan(): void
    {
        $check = (new DeletedScheduledCommandCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Console']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsString('deleted:command', $result->locations[0]['issue'] ?? '');
    }
}
