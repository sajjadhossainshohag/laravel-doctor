<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Jobs;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Jobs\JobTriesZeroCheck;
use SajjadHossain\Doctor\Enums\Severity;

class JobTriesZeroCheckTest extends TestCase
{
    /** @test */
    public function it_detects_job_with_tries_set_to_zero(): void
    {
        $check = (new JobTriesZeroCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Jobs']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning, 'tries');
    }

    /** @test */
    public function it_passes_when_no_jobs_with_tries_zero(): void
    {
        $check = (new JobTriesZeroCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Providers']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }
}
