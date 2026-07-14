<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Eloquent;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Eloquent\ValueVsFirstOnNullCheck;
use SajjadHossain\Doctor\Enums\Severity;

class ValueVsFirstOnNullCheckTest extends TestCase
{
    /** @test */
    public function it_detects_unguarded_first_property_call(): void
    {
        config()->set('doctor.scan_paths', [
            __DIR__.'/../../../Fixtures/App/Isolated/ValueVsFirst',
        ]);

        $result = (new ValueVsFirstOnNullCheck())->run();

        $this->assertCheckFailed($result, Severity::Error);
        $this->assertStringContainsString('unsafe', strtolower($result->message));
    }

    /** @test */
    public function it_skips_if_guarded_first_property_call(): void
    {
        config()->set('doctor.scan_paths', [
            __DIR__.'/../../../Fixtures/App/Isolated/ValueVsFirst',
        ]);

        $result = (new ValueVsFirstOnNullCheck())->run();

        // The guarded one should be skipped; only the unguarded one flagged
        $files = array_column($result->locations, 'file');
        $flagCount = 0;
        foreach ($files as $f) {
            if (str_contains($f, 'GuardedFirstCall')) {
                $flagCount++;
            }
        }
        $this->assertEquals(0, $flagCount, 'GuardedFirstCall should not be flagged');
    }

    /** @test */
    public function it_passes_when_no_first_calls(): void
    {
        config()->set('doctor.scan_paths', [
            __DIR__.'/../../../Fixtures/App/Isolated/ValueVsFirst/CleanNoFirstCall.php',
        ]);

        $result = (new ValueVsFirstOnNullCheck())->run();

        $this->assertCheckPassed($result);
    }
}
