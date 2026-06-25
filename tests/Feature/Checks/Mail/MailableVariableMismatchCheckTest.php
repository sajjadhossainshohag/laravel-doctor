<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Mail;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Mail\MailableVariableMismatchCheck;
use SajjadHossain\Doctor\Enums\Severity;

class MailableVariableMismatchCheckTest extends TestCase
{
    /**
     * Helper: invoke check, return locations.
     *
     * @return array<int, array{file: string, issue: string}>
     */
    private function locationsFor(string $path): array
    {
        // Ensure the package's view finder can resolve the test view
        // directory under tests/Fixtures/Views.
        config([
            'view.paths' => array_merge(
                [__DIR__.'/../../../Fixtures/Views'],
                config('view.paths', [])
            ),
        ]);

        $check = (new MailableVariableMismatchCheck())
            ->withPaths([$path]);

        return $check->run()->locations;
    }

    /** @test */
    public function it_detects_mismatch_with_modern_named_argument_content_api(): void
    {
        // Bug regression: previously the check only supported
        // `'view' => 'foo'` (array-key form). With the modern
        // named-argument form `new Content(view: 'foo')`, firstViewName()
        // returned null and the file was skipped, hiding the mismatch.
        $locations = $this->locationsFor(
            __DIR__.'/../../../Fixtures/App/Mail/Isolated/VariableMismatchNamedArg'
        );

        $this->assertNotEmpty($locations, 'Expected at least one mismatch issue');
        $issues = array_column($locations, 'issue');
        $found = false;
        foreach ($issues as $issue) {
            if (str_contains($issue, 'unusedVar')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected to flag unused variable in named-arg mailable');
    }

    /** @test */
    public function it_detects_mismatch_with_modern_array_key_content_api(): void
    {
        $locations = $this->locationsFor(
            __DIR__.'/../../../Fixtures/App/Mail/Isolated/VariableMismatchArrayForm'
        );

        $this->assertNotEmpty($locations);
        $issues = array_column($locations, 'issue');
        $found = false;
        foreach ($issues as $issue) {
            if (str_contains($issue, 'unusedVar')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected to flag unused variable in array-form mailable');
    }

    /** @test */
    public function it_does_not_flag_when_all_variables_are_used(): void
    {
        // Negative case: variable passed via with() IS used in view.
        $locations = $this->locationsFor(
            __DIR__.'/../../../Fixtures/App/Mail/Isolated/VariableMismatchNoUnused'
        );

        $this->assertEmpty($locations, 'Expected no issues but got: '.json_encode($locations));
    }
}