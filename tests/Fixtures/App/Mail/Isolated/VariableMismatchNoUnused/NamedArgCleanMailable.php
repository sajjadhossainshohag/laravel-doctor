<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Mail\Isolated\VariableMismatchNoUnused;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Negative case: modern Content API with named args, AND the variable
 * passed via with() IS actually used in the view. Should NOT flag.
 */
class NamedArgCleanMailable extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.welcome-clean');
    }

    public function build(): self
    {
        return $this->with(['used' => 'value']);
    }
}