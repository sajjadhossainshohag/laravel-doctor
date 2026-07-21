<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Debug;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Debug\DebugStatementLeftInCheck;
use SajjadHossain\Doctor\Enums\Severity;

class DebugStatementLeftInCheckTest extends TestCase
{
    /** @test */
    public function it_detects_all_debug_functions(): void
    {
        $check = (new DebugStatementLeftInCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Debug']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Error);

        $functions = array_unique(array_column($result->locations, 'function'));
        sort($functions);
        $this->assertSame(['dd', 'ddd', 'die/exit', 'dump', 'phpinfo', 'print_r', 'ray', 'var_dump'], $functions);
    }

    /** @test */
    public function it_detects_fully_qualified_debug_calls(): void
    {
        $check = (new DebugStatementLeftInCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Debug/FullyQualified']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Error);
        $this->assertCount(7, $result->locations);

        $functions = array_unique(array_column($result->locations, 'function'));
        sort($functions);
        $this->assertSame(['dd', 'ddd', 'dump', 'phpinfo', 'print_r', 'ray', 'var_dump'], $functions);
    }

    /** @test */
    public function it_detects_die_and_exit(): void
    {
        $check = (new DebugStatementLeftInCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Debug']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Error);

        $functions = array_unique(array_column($result->locations, 'function'));
        $this->assertContains('die/exit', $functions);
    }

    /** @test */
    public function it_detects_debug_in_blade_views(): void
    {
        $check = (new DebugStatementLeftInCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Debug']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Error);

        $bladeFiles = array_filter($result->locations, fn($loc) => str_ends_with($loc['file'], '.blade.php'));
        $this->assertNotEmpty($bladeFiles, 'Expected debug calls in Blade files to be detected');
    }

    /** @test */
    public function it_passes_on_clean_controller(): void
    {
        $check = (new DebugStatementLeftInCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Http/Controllers']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }
}
