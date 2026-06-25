<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Livewire;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Livewire\MissingLivewireActionMethodCheck;
use SajjadHossain\Doctor\Enums\Severity;

class MissingLivewireActionMethodCheckTest extends TestCase
{
    /**
     * Helper: invoke check with a list of paths and return locations.
     *
     * @param  array<int, string>  $paths
     * @return array<int, array{file?: string, component?: string, issue?: string}>
     */
    private function locationsFor(array $paths): array
    {
        // Add the package's view directory so the check can find
        // fixture Blade views.
        config([
            'view.paths' => array_merge(
                [__DIR__.'/../../../Fixtures/Views'],
                config('view.paths', [])
            ),
        ]);

        $check = (new MissingLivewireActionMethodCheck())
            ->withPaths($paths);

        return $check->run()->locations;
    }

    /** @test */
    public function it_detects_missing_action_in_livewire_v3_path(): void
    {
        // app/Livewire/.../V3BrokenCounter — wire:click=nonExistentMethod
        // must be flagged.
        $locations = $this->locationsFor([
            __DIR__.'/../../../Fixtures/App/Livewire/V3/BrokenCounter',
        ]);

        $this->assertNotEmpty($locations);
        $issues = array_column($locations, 'issue');
        $found = false;
        foreach ($issues as $issue) {
            if (str_contains($issue, 'nonExistentMethod')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected to flag nonExistentMethod in v3 component');
    }

    /** @test */
    public function it_detects_missing_action_in_livewire_v2_path(): void
    {
        // app/Http/Livewire/.../V2BrokenCounter — the OLD hardcoded
        // path missed this entirely. The NEW check picks up BOTH v2
        // (app/Http/Livewire) and v3 (app/Livewire) default locations.
        $locations = $this->locationsFor([
            __DIR__.'/../../../Fixtures/App/Http/Livewire/V2/BrokenCounter',
        ]);

        $this->assertNotEmpty($locations);
        $issues = array_column($locations, 'issue');
        $found = false;
        foreach ($issues as $issue) {
            if (str_contains($issue, 'nonExistentMethod')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected to flag nonExistentMethod in v2 component');
    }

    /** @test */
    public function it_supports_mixed_project_layouts_via_explicit_paths(): void
    {
        // A project with components in non-standard locations can
        // pass them in via withPaths() — the OLD hardcoded paths
        // couldn't.
        $locations = $this->locationsFor([
            __DIR__.'/../../../Fixtures/App/Mixed/BrokenCounter',
        ]);

        $this->assertNotEmpty($locations);
        $issues = array_column($locations, 'issue');
        $found = false;
        foreach ($issues as $issue) {
            if (str_contains($issue, 'nonExistentMethod')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected to flag nonExistentMethod in mixed layout');
    }
}