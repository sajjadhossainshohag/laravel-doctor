<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Views;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Views\MissingIncludeCheck;
use SajjadHossain\Doctor\Enums\Severity;

class MissingIncludeCheckTest extends TestCase
{
    /** @test */
    public function it_detects_missing_include_views(): void
    {
        config()->set('view.paths', [__DIR__.'/../../../Fixtures/Views/broken']);

        $result = (new MissingIncludeCheck())->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsString('missing', strtolower($result->message));
    }

    /** @test */
    public function it_passes_when_includes_exist(): void
    {
        config()->set('view.paths', [__DIR__.'/../../../Fixtures/Views/good']);

        $result = (new MissingIncludeCheck())->run();

        $this->assertCheckPassed($result);
    }
}
