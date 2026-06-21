<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Mail;

use Illuminate\Mail\Mailable;

class BrokenMailableLegacy extends Mailable
{
    public function build()
    {
        return $this->view('emails.this-view-does-not-exist');
    }
}
