<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Components;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Components\AnonymousComponentCheck;
use SajjadHossain\Doctor\Enums\Severity;

class AnonymousComponentCheckTest extends TestCase
{
    /** @test */
    public function it_passes_when_anonymous_component_paths_exist(): void
    {
        $result = (new AnonymousComponentCheck())->run();

        // All paths set up by Orchestra Testbench at this point exist
        $this->assertCheckPassed($result);
    }
}
