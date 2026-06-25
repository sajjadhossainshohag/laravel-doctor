<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Mail\Isolated\HtmlRaw;

use Illuminate\Mail\Mailable;

/**
 * ->html('<h1>Hello</h1>') takes raw HTML, NOT a view name.
 * This mailable must NOT be flagged as referencing a missing view,
 * even though '<h1>Hello</h1>' looks like a (non-existent) view path.
 */
class GoodHtmlRawMailable extends Mailable
{
    public function build()
    {
        return $this->html('<h1>Hello world</h1><p>This is raw HTML.</p>');
    }
}