<?php

namespace App\Http\Controllers;

class DebugController
{
    public function index()
    {
        $users = ['Alice', 'Bob'];

        dd($users);

        ddd($users);

        dump($users);

        var_dump($users);

        ray($users);

        print_r($users);

        phpinfo();
    }
}
