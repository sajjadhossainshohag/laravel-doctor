<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Mail;

use Illuminate\Mail\Mailable;

class GoodMailableLegacy extends Mailable
{
    public function build()
    {
        return $this->view('good.index');
    }
}
