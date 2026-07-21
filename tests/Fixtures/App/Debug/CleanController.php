<?php

namespace App\Http\Controllers;

class CleanController
{
    public function index()
    {
        $users = ['Alice', 'Bob'];

        return view('users', compact('users'));
    }
}
