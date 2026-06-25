<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Mail\Isolated\ModernHtmlRaw;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Modern Content API using 'html' key (raw HTML — NOT a view).
 * Even with named-argument syntax, html: '<p>...</p>' takes raw HTML
 * and must not be treated as a view name.
 */
class GoodModernHtmlRawMailable extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Modern html raw');
    }

    public function content(): Content
    {
        return new Content(html: '<h1>Hello</h1><p>Raw HTML in named arg.</p>');
    }
}