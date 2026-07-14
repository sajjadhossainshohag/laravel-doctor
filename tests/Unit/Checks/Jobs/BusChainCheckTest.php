<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Jobs;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Jobs\BusChainCheck;
use SajjadHossain\Doctor\Enums\Severity;

class BusChainCheckTest extends TestCase
{
    /** @test */
    public function it_detects_non_existent_jobs_in_bus_chain(): void
    {
        config()->set('doctor.scan_paths', [
            __DIR__.'/../../../Fixtures/App/Http/Controllers',
        ]);

        $result = (new BusChainCheck())->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsString('NonExistentJob', $result->locations[0]['job'] ?? '');
    }
}
