<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Components;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Components\ComponentNamespaceCheck;
use SajjadHossain\Doctor\Enums\Severity;

class ComponentNamespaceCheckTest extends TestCase
{
    /** @test */
    public function it_passes_when_namespace_paths_exist(): void
    {
        $result = (new ComponentNamespaceCheck())->run();

        // Default testbench namespace hints exist — should pass
        $this->assertCheckPassed($result);
    }
}
