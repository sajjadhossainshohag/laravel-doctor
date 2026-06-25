<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Mail\Isolated\ContentNamedMissing;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Modern Content API using NAMED ARGUMENTS with a non-existent view.
 * Must be detected and flagged (the view 'broken.named-missing' does
 * not exist).
 */
class BrokenContentNamedViewMailable extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Named arg broken');
    }

    public function content(): Content
    {
        return new Content(view: 'broken.named-missing');
    }
}