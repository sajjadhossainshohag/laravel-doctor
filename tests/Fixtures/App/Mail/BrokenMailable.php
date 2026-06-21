<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BrokenMailable extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Broken Mail');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.this-view-does-not-exist');
    }
}
