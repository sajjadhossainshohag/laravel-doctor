<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Mail\Isolated\ContentArrayView;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Modern Content API using ARRAY KEY form: new Content(['view' => 'foo'])
 * Must still be detected and validated.
 */
class GoodContentArrayViewMailable extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Array key good');
    }

    public function content(): Content
    {
        return new Content(['view' => 'good.index']);
    }
}