<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Eloquent;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Eloquent\SoftDeleteScopeConflictCheck;
use SajjadHossain\Doctor\Enums\Severity;

class SoftDeleteScopeConflictCheckTest extends TestCase
{
    /** @test */
    public function it_detects_manual_deleted_at_filter(): void
    {
        $check = (new SoftDeleteScopeConflictCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Models/Broken']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning, 'deleted_at');
    }

    /** @test */
    public function it_passes_for_model_without_deleted_at_queries(): void
    {
        $check = (new SoftDeleteScopeConflictCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Models/Good']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }
}
