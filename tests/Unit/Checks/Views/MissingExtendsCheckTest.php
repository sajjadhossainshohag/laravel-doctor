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

    /** @test */
    public function it_resolves_namespaced_extends_via_hints(): void
    {
        $nsPath = __DIR__.'/../../../Fixtures/Views/ns-layouts';

        app('view')->addNamespace('testns', $nsPath);

        config()->set('view.paths', [__DIR__.'/../../../Fixtures/Views/namespaced']);

        $result = (new MissingExtendsCheck())->run();

        $this->assertCheckPassed($result, 'Namespaced @extends should resolve via registered hint');
    }

    /** @test */
    public function it_detects_missing_layout_even_with_registered_namespace_hint(): void
    {
        $nsPath = __DIR__.'/../../../Fixtures/Views/ns-layouts';
        app('view')->addNamespace('testns', $nsPath);

        config()->set('view.paths', [__DIR__.'/../../../Fixtures/Views/namespaced_missing']);

        // extends-ns-missing.blade.php uses @extends('testns::layouts.missing')
        // — the hint IS registered, but 'layouts/missing.blade.php' does
        // NOT exist under the hinted directory.  This should still fail.
        $result = (new MissingExtendsCheck())->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsString('not found', strtolower($result->message));
    }
}
