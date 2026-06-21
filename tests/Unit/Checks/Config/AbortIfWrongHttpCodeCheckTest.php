<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Config;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Config\AbortIfWrongHttpCodeCheck;
use SajjadHossain\Doctor\Enums\Severity;

class AbortIfWrongHttpCodeCheckTest extends TestCase
{
    /** @test */
    public function it_detects_abort_if_called_with_success_status_code(): void
    {
        $check = (new AbortIfWrongHttpCodeCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Http/Controllers']); // has abort_if(..., 100) 

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertNotEmpty($result->locations);
        $this->assertStringContainsString('100', $result->locations[0]['issue'] ?? '');
    }
}
