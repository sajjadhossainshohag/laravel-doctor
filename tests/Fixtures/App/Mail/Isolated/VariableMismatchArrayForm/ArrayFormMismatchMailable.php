<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Mail\Isolated\VariableMismatchArrayForm;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Modern Content API with ARRAY-KEY form. View uses $greeting but
 * mailable passes $unusedVar via ->with(). Should still be detected.
 */
class ArrayFormMismatchMailable extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome');
    }

    public function content(): Content
    {
        return new Content(['view' => 'emails.welcome-array']);
    }

    public function build(): self
    {
        return $this->with(['unusedVar' => 'value']);
    }
}