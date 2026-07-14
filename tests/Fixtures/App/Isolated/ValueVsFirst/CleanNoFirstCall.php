<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Isolated\ValueVsFirst;

class CleanNoFirstCall
{
    public function load()
    {
        return \App\Models\User::all();
    }
}
