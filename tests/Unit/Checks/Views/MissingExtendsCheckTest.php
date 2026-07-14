<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Views;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Views\MissingExtendsCheck;
use SajjadHossain\Doctor\Enums\Severity;

class MissingExtendsCheckTest extends TestCase
{
    /** @test */
    public function it_detects_missing_extends_layouts(): void
    {
        config()->set('view.paths', [__DIR__.'/../../../Fixtures/Views/broken']);

        $result = (new MissingExtendsCheck())->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsString('not found', strtolower($result->message));
    }

    /** @test */
    public function it_passes_when_layouts_exist(): void
    {
        config()->set('view.paths', [__DIR__.'/../../../Fixtures/Views/good']);

        $result = (new MissingExtendsCheck())->run();

        $this->assertCheckPassed($result);
    }
}
