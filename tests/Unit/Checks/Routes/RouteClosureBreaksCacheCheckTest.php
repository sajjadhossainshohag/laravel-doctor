<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Routes;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Routes\RouteClosureBreaksCacheCheck;
use SajjadHossain\Doctor\Enums\Severity;

class RouteClosureBreaksCacheCheckTest extends TestCase
{
    /** @test */
    public function it_detects_closure_in_route_definition(): void
    {
        $check = (new RouteClosureBreaksCacheCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/routes/closure']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsStringIgnoringCase('closure', $result->message);
    }

    /** @test */
    public function it_detects_arrow_function_in_route_definition(): void
    {
        $check = (new RouteClosureBreaksCacheCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/routes/arrow']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsStringIgnoringCase('closure', $result->message);
        $this->assertSame('/profile', $result->locations[0]['uri']);
    }

    /** @test */
    public function it_passes_with_controller_string(): void
    {
        $check = (new RouteClosureBreaksCacheCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/routes/controller']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_detects_multiple_closure_routes(): void
    {
        $check = (new RouteClosureBreaksCacheCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/routes/multi_closures']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertCount(3, $result->locations);
    }

    /** @test */
    public function it_detects_closure_in_post_route(): void
    {
        $check = (new RouteClosureBreaksCacheCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/routes/post']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertSame('post', $result->locations[0]['method']);
        $this->assertSame('/data', $result->locations[0]['uri']);
    }

    /** @test */
    public function it_detects_closure_in_match_route(): void
    {
        $check = (new RouteClosureBreaksCacheCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/routes/match']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertSame('match', $result->locations[0]['method']);
        $this->assertSame('/submit', $result->locations[0]['uri']);
    }
}
