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

    /** @test */
    public function it_does_not_flag_legacy_html_raw_string_as_view(): void
    {
        // ->html('<h1>Hello</h1>') is raw HTML, NOT a view name.
        // The check must not flag this even though '<h1>Hello</h1>'
        // doesn't exist as a view.
        $this->app['view']->addLocation(__DIR__.'/../../../Fixtures/Views');

        $check = (new MailableMissingViewCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Mail/Isolated/HtmlRaw']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_does_not_flag_modern_html_named_arg_as_view(): void
    {
        // new Content(html: '<p>...</p>') is raw HTML, not a view.
        $this->app['view']->addLocation(__DIR__.'/../../../Fixtures/Views');

        $check = (new MailableMissingViewCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Mail/Isolated/ModernHtmlRaw']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_detects_modern_content_named_arg_view_reference(): void
    {
        // new Content(view: 'broken.named-missing') — named arg with
        // non-existent view MUST be flagged.
        $this->app['view']->addLocation(__DIR__.'/../../../Fixtures/Views');

        $check = (new MailableMissingViewCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Mail/Isolated/ContentNamedMissing']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsString('broken.named-missing', $result->locations[0]['issue'] ?? '');
    }

    /** @test */
    public function it_passes_for_modern_content_named_arg_with_existing_view(): void
    {
        // new Content(view: 'good.index') — named arg with existing view.
        $this->app['view']->addLocation(__DIR__.'/../../../Fixtures/Views');

        $check = (new MailableMissingViewCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Mail/Isolated/ContentNamedView']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_passes_for_modern_content_array_form_with_existing_view(): void
    {
        // new Content(['view' => 'good.index']) — array form with existing view.
        $this->app['view']->addLocation(__DIR__.'/../../../Fixtures/Views');

        $check = (new MailableMissingViewCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Mail/Isolated/ContentArrayView']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_passes_for_legacy_markdown_form_with_existing_view(): void
    {
        // ->markdown('good.index') must be detected and validated.
        $this->app['view']->addLocation(__DIR__.'/../../../Fixtures/Views');

        $check = (new MailableMissingViewCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Mail/Isolated/MarkdownLegacy']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_passes_for_legacy_text_form_with_existing_view(): void
    {
        // ->text('good.index') must be detected and validated.
        $this->app['view']->addLocation(__DIR__.'/../../../Fixtures/Views');

        $check = (new MailableMissingViewCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Mail/Isolated/TextLegacy']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }
}