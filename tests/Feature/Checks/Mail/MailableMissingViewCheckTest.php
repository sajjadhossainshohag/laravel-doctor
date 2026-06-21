<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Mail;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Mail\MailableMissingViewCheck;
use SajjadHossain\Doctor\Enums\Severity;

class MailableMissingViewCheckTest extends TestCase
{
    /** @test */
    public function it_detects_mailable_referencing_missing_view(): void
    {
        $check = (new MailableMissingViewCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Mail/Fail']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertNotEmpty($result->locations);
        $this->assertStringContainsString('this-view-does-not-exist', $result->locations[0]['issue'] ?? '');
    }

    /** @test */
    public function it_passes_for_mailable_with_existing_view(): void
    {
        $this->app['view']->addLocation(__DIR__.'/../../../Fixtures/Views');

        $check = (new MailableMissingViewCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Mail/Pass']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }
}
