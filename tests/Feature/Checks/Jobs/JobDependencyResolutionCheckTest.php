<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Jobs;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Jobs\JobDependencyResolutionCheck;
use SajjadHossain\Doctor\Enums\Severity;

class JobDependencyResolutionCheckTest extends TestCase
{
    /** @test */
    public function it_detects_closure_typed_constructor_parameter(): void
    {
        // Ensure bad job class is loaded
        require_once __DIR__.'/../../../Fixtures/App/Jobs/ClosureTypedJob.php';

        $result = (new JobDependencyResolutionCheck())->run();

        $this->assertCheckFailed($result, Severity::Warning);
    }

}
