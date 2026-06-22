<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Eloquent;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Eloquent\AccessorMutatorStyleConflictCheck;
use SajjadHossain\Doctor\Enums\Severity;

class AccessorMutatorStyleConflictCheckTest extends TestCase
{
    /** @test */
    public function it_detects_old_and_new_accessor_style_on_same_property(): void
    {
        $check = (new AccessorMutatorStyleConflictCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Models/Broken']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Info, 'accessor');
    }

    /** @test */
    public function it_passes_for_model_using_only_new_attribute_style(): void
    {
        $check = (new AccessorMutatorStyleConflictCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Models/Good']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }
}
