<?php

namespace SajjadHossain\Doctor\Tests\Unit;

use Illuminate\Console\OutputStyle;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\Output\ConsoleRenderer;
use SajjadHossain\Doctor\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ConsoleRendererTest extends TestCase
{
    private function captureRender(array $results, float $duration = 0): string
    {
        $buffered = new BufferedOutput();
        $output = new OutputStyle(new ArrayInput([]), $buffered);
        (new ConsoleRenderer())->render($output, $results, $duration);

        return $buffered->fetch();
    }

    private function makeResult(
        bool $passed,
        Severity $severity = Severity::Warning,
        string $check = 'Test Check',
        string $message = 'Test message',
    ): CheckResult {
        return new CheckResult(
            check: $check,
            category: 'test',
            severity: $severity,
            passed: $passed,
            message: $message,
        );
    }

    /** @test */
    public function it_shows_all_passed_summary(): void
    {
        $output = $this->captureRender([
            $this->makeResult(true),
            $this->makeResult(true),
        ]);

        $this->assertStringContainsString('Results: 2 passed', $output);
        $this->assertStringNotContainsString('error', $output);
        $this->assertStringNotContainsString('warning', $output);
        $this->assertStringNotContainsString('info', $output);
    }

    /** @test */
    public function it_shows_error_and_warning_breakdown(): void
    {
        $output = $this->captureRender([
            $this->makeResult(true),
            $this->makeResult(false, Severity::Error, 'Error Check'),
            $this->makeResult(false, Severity::Warning, 'Warning Check'),
        ]);

        $this->assertStringContainsString('Results: 1 passed, 1 errors, 1 warnings', $output);
    }

    /** @test */
    public function it_shows_info_when_present(): void
    {
        $output = $this->captureRender([
            $this->makeResult(true),
            $this->makeResult(false, Severity::Info, 'Info Check'),
        ]);

        $this->assertStringContainsString('Results: 1 passed, 1 info', $output);
    }

    /** @test */
    public function it_omits_severity_with_zero_count(): void
    {
        $output = $this->captureRender([
            $this->makeResult(false, Severity::Error),
            $this->makeResult(false, Severity::Error),
        ]);

        $this->assertStringContainsString('Results: 0 passed, 2 errors', $output);
        $this->assertStringNotContainsString('warnings', $output);
        $this->assertStringNotContainsString('info', $output);
    }

    /** @test */
    public function it_includes_duration_when_provided(): void
    {
        $output = $this->captureRender([
            $this->makeResult(true),
        ], duration: 2500);

        $this->assertStringContainsString('Results: 1 passed (2.50s)', $output);
    }

    /** @test */
    public function it_omits_duration_when_zero(): void
    {
        $output = $this->captureRender([
            $this->makeResult(true),
        ]);

        $this->assertStringContainsString('Results: 1 passed', $output);
        $this->assertStringNotContainsString('(0.00s)', $output);
    }
}
