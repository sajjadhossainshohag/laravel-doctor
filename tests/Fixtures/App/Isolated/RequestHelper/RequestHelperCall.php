<?php

namespace App\Http\Controllers;

use App\Models\User;

class HelperController
{
    public function all()
    {
        return User::create(request()->all());
    }

    public function input()
    {
        return User::create(request()->input());
    }

    public function post()
    {
        return User::create(request()->post());
    }

    public function query()
    {
        return User::create(request()->query());
    }
}
