<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Routes;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Routes\DuplicateUrisCheck;
use SajjadHossain\Doctor\Enums\Severity;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

class DuplicateUrisCheckTest extends TestCase
{
    /** @test */
    public function it_passes_when_all_uris_are_unique(): void
    {
        RouteFacade::get('/unique-get', fn () => '')->name('unique.get');
        RouteFacade::post('/unique-post', fn () => '')->name('unique.post');

        $result = (new DuplicateUrisCheck())->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_detects_duplicate_uris_with_same_method(): void
    {
        $routeA = new Route('GET', '/dashboard', ['uses' => fn () => '']);
        $routeB = new Route('GET', '/dashboard', ['uses' => fn () => '']);

        $result = (new DuplicateUrisCheck())
            ->withRoutes([$routeA, $routeB])
            ->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsString('1 duplicate route', $result->message);
        $this->assertCount(1, $result->locations);
        $this->assertSame('dashboard', $result->locations[0]['uri']);
    }

    /** @test */
    public function it_ignores_different_methods_on_same_uri(): void
    {
        $routeA = new Route('GET', '/dashboard', ['uses' => fn () => '']);
        $routeB = new Route('POST', '/dashboard', ['uses' => fn () => '']);

        $result = (new DuplicateUrisCheck())
            ->withRoutes([$routeA, $routeB])
            ->run();

        $this->assertCheckPassed($result);
    }
}
