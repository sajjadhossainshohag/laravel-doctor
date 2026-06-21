<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class GoodMailable extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Good Mail');
    }

    public function content(): Content
    {
        return new Content(view: 'good.index');
    }
}
