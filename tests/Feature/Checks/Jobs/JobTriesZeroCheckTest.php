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

    /** @test */
    public function it_still_flags_tries_zero_when_only_backoff_property_is_set(): void
    {
        // Bug regression: $tries = 0 + $backoff = N is NOT a safe
        // combination — $backoff is a delay, not a retry cap.
        $check = (new JobTriesZeroCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Jobs/Isolated/BackoffOnly']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        // Issue message should explicitly mention that $backoff is a
        // delay, not a cap, so the developer understands.
        $this->assertStringContainsString('backoff', $result->locations[0]['issue'] ?? '');
    }

    /** @test */
    public function it_still_flags_tries_zero_when_only_backoff_method_is_defined(): void
    {
        // Same regression: backoff() method (no retryUntil/tries()) is
        // not a retry cap.
        $check = (new JobTriesZeroCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Jobs/Isolated/BackoffMethod']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
    }

    /** @test */
    public function it_passes_when_retryUntil_method_is_defined(): void
    {
        // $tries = 0 + retryUntil() is intentional.
        $check = (new JobTriesZeroCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Jobs/Isolated/RetryUntil']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_passes_when_tries_method_is_defined(): void
    {
        // $tries = 0 + tries() method (overrides the property) is
        // intentional.
        $check = (new JobTriesZeroCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Jobs/Isolated/TriesMethod']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }
}