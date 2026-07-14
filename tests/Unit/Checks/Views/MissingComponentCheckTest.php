<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Views;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Views\MissingComponentCheck;
use SajjadHossain\Doctor\Enums\Severity;

class MissingComponentCheckTest extends TestCase
{
    /** @test */
    public function it_detects_missing_component_references(): void
    {
        config()->set('view.paths', [__DIR__.'/../../../Fixtures/Views/broken']);

        $result = (new MissingComponentCheck())->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsString('point to missing views', strtolower($result->message));
    }

    /** @test */
    public function it_passes_when_components_exist(): void
    {
        config()->set('view.paths', [__DIR__.'/../../../Fixtures/Views/good']);

        $result = (new MissingComponentCheck())->run();

        $this->assertCheckPassed($result);
    }
}
