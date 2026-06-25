<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Mail\Isolated\ContentNamedView;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Modern Content API using NAMED ARGUMENTS (PHP 8): new Content(view: 'foo')
 * Must be detected and validated. The view 'good.index' exists, so this
 * is a "good" mailable and should not be flagged.
 */
class GoodContentNamedViewMailable extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Named arg good');
    }

    public function content(): Content
    {
        return new Content(view: 'good.index');
    }
}