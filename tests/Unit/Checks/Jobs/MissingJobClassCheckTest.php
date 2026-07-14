<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Jobs;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Jobs\MissingJobClassCheck;
use SajjadHossain\Doctor\Enums\Severity;

class MissingJobClassCheckTest extends TestCase
{
    /** @test */
    public function it_detects_dispatch_calls_to_non_existent_jobs(): void
    {
        config()->set('doctor.scan_paths', [
            __DIR__.'/../../../Fixtures/App/Http/Controllers',
        ]);

        $result = (new MissingJobClassCheck())->run();

        $this->assertCheckFailed($result, Severity::Error);
        $this->assertStringContainsString('NonExistentJob', $result->locations[0]['job'] ?? '');
    }
}
