<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Http\Controllers;

class AbortController
{
    public function index()
    {
        abort_if(empty($data), 100);
        return response()->json(['ok' => true]);
    }
}
