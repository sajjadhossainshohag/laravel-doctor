<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Eloquent;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Eloquent\MissingGuardedOrFillableCheck;
use SajjadHossain\Doctor\Enums\Severity;

class MissingGuardedOrFillableCheckTest extends TestCase
{
    /** @test */
    public function it_detects_model_with_neither_fillable_nor_guarded(): void
    {
        $check = (new MissingGuardedOrFillableCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Models/Broken']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Error);
        $this->assertNotEmpty($result->locations);
        $this->assertStringContainsString('NoFillableModel', $result->locations[0]['issue'] ?? '');
    }

    /** @test */
    public function it_passes_for_model_with_fillable_defined(): void
    {
        $check = (new MissingGuardedOrFillableCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Models/Good']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }
}
