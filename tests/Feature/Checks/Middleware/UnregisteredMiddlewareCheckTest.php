<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Middleware;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Middleware\UnregisteredMiddlewareCheck;
use SajjadHossain\Doctor\Enums\Severity;

class UnregisteredMiddlewareCheckTest extends TestCase
{
    /** @test */
    public function it_detects_middleware_alias_on_route_not_registered_in_app(): void
    {
        $bootstrapFixture = __DIR__.'/../../../Fixtures/App/bootstrap/app.php';
        $routeFixture = __DIR__.'/../../../Fixtures/App/routes';

        $check = (new UnregisteredMiddlewareCheck())
            ->withBootstrapPath($bootstrapFixture)
            ->withPaths([$routeFixture]);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertNotEmpty($result->locations);
        $this->assertStringContainsString('non.existent.alias', $result->locations[0]['issue'] ?? '');
    }

    /** @test */
    public function it_passes_when_all_middleware_aliases_are_registered(): void
    {
        $bootstrapFixture = __DIR__.'/../../../Fixtures/App/bootstrap/app.php';

        $check = (new UnregisteredMiddlewareCheck())
            ->withBootstrapPath($bootstrapFixture)
            ->withPaths([__DIR__.'/../../../Fixtures/Views/good']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }
}
