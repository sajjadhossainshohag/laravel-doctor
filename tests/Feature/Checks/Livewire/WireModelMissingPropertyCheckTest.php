<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Livewire;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Livewire\WireModelMissingPropertyCheck;
use SajjadHossain\Doctor\Enums\Severity;

class WireModelMissingPropertyCheckTest extends TestCase
{
    /**
     * @param  array<int, string>  $paths
     * @return array<int, array{file?: string, component?: string, issue?: string}>
     */
    private function locationsFor(array $paths): array
    {
        config([
            'view.paths' => array_merge(
                [__DIR__.'/../../../Fixtures/Views'],
                config('view.paths', [])
            ),
        ]);

        $check = (new WireModelMissingPropertyCheck())
            ->withPaths($paths);

        return $check->run()->locations;
    }

    /** @test */
    public function it_detects_missing_property_in_livewire_v3_path(): void
    {
        $locations = $this->locationsFor([
            __DIR__.'/../../../Fixtures/App/Livewire/V3/WireModel',
        ]);

        $this->assertNotEmpty($locations);
        $issues = array_column($locations, 'issue');
        $found = false;
        foreach ($issues as $issue) {
            if (str_contains($issue, 'missingProp')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected to flag missingProp in v3 component');
    }

    /** @test */
    public function it_detects_missing_property_in_livewire_v2_path(): void
    {
        // Bug regression: with OLD hardcoded app/Livewire path, v2
        // components were silently skipped.
        $locations = $this->locationsFor([
            __DIR__.'/../../../Fixtures/App/Http/Livewire/V2/WireModel',
        ]);

        $this->assertNotEmpty($locations);
        $issues = array_column($locations, 'issue');
        $found = false;
        foreach ($issues as $issue) {
            if (str_contains($issue, 'missingProp')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected to flag missingProp in v2 component');
    }

    /** @test */
    public function it_supports_mixed_project_layouts_via_explicit_paths(): void
    {
        $locations = $this->locationsFor([
            __DIR__.'/../../../Fixtures/App/Mixed/WireModel',
        ]);

        $this->assertNotEmpty($locations);
        $issues = array_column($locations, 'issue');
        $found = false;
        foreach ($issues as $issue) {
            if (str_contains($issue, 'missingProp')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected to flag missingProp in mixed layout');
    }
}