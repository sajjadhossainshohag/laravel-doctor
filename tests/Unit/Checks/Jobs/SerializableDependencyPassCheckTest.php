<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Jobs;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Jobs\JobDependencyResolutionCheck;

class SerializableDependencyPassCheckTest extends TestCase
{
    /** @test */
    public function it_passes_for_serializable_constructor_parameters(): void
    {
        require_once __DIR__.'/../../../Fixtures/App/Jobs/SerializableTypedJob.php';

        $result = (new JobDependencyResolutionCheck())->run();

        $this->assertCheckPassed($result);
    }
}
