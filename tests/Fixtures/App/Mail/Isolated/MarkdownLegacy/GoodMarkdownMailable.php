<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Mail\Isolated\MarkdownLegacy;

use Illuminate\Mail\Mailable;

/**
 * Legacy fluent ->markdown('name') form. 'good.index' exists.
 */
class GoodMarkdownMailable extends Mailable
{
    public function build()
    {
        return $this->markdown('good.index');
    }
}