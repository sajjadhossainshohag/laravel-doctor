<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Mail\Isolated\TextLegacy;

use Illuminate\Mail\Mailable;

/**
 * Legacy fluent ->text('name') form. 'good.index' exists.
 */
class GoodTextMailable extends Mailable
{
    public function build()
    {
        return $this->text('good.index');
    }
}