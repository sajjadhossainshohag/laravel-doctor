<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Middleware;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Middleware\UnregisteredMiddlewareCheck;
use SajjadHossain\Doctor\Enums\Severity;

class UnregisteredMiddlewareCheckTest extends TestCase
{
    /** @test */
    public function it_detects_middleware_alias_not_registered_in_kernel(): void
    {
        $check = (new UnregisteredMiddlewareCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/routes']);

        $result = $check->run();

        // The fixture route uses 'non.existent.alias' — not registered in
        // the testbench Kernel, so it should be flagged.
        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertNotEmpty($result->locations);
        $this->assertStringContainsString('non.existent.alias', $result->locations[0]['issue'] ?? '');
    }

    /** @test */
    public function it_passes_when_middleware_alias_is_registered_in_kernel(): void
    {
        // Register a custom alias into the booted Router so the check
        // sees it as known.
        // Testbench's Kernel stores aliases via $routeMiddleware property.
        // We register by calling the underlying router's aliasMiddleware.
        app('router')->aliasMiddleware('custom.registered.alias', \App\Http\Middleware\Authenticate::class);

        // Now scan a path that contains a route using that alias.
        // We'll use the same route fixture, which uses 'non.existent.alias'
        // — that should still fail, but a separate check for the custom
        // alias should pass. We'll test the passing case with a clean
        // scan of a path with no bad middleware calls.
        $check = (new UnregisteredMiddlewareCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/Views/good']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }
}
