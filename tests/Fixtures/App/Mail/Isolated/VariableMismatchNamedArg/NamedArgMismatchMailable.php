<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Mail\Isolated\VariableMismatchNamedArg;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Modern Content API with NAMED ARGUMENTS (PHP 8). The view uses $name
 * but the mailable passes $unusedVar via ->with(...). With the OLD bug
 * (only array-key form supported), firstViewName() would return null
 * here, the file would be skipped, and the mismatch would never be
 * detected.
 */
class NamedArgMismatchMailable extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.welcome-named');
    }

    public function build(): self
    {
        return $this->with(['unusedVar' => 'value']);
    }
}