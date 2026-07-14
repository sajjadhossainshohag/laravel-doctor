<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Livewire;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Livewire\MissingLivewireComponentCheck;
use SajjadHossain\Doctor\Enums\Severity;

class MissingLivewireComponentCheckTest extends TestCase
{
    /** @test */
    public function it_detects_missing_livewire_component_class(): void
    {
        config()->set('view.paths', [
            __DIR__.'/../../../Fixtures/Views/livewire',
        ]);

        $result = (new MissingLivewireComponentCheck())->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsString('missing', strtolower($result->message));
    }

    /** @test */
    public function it_passes_when_no_livewire_tags(): void
    {
        config()->set('view.paths', [
            __DIR__.'/../../../Fixtures/Views/livewire/clean.blade.php',
        ]);

        $result = (new MissingLivewireComponentCheck())->run();

        $this->assertCheckPassed($result);
    }
}
