<?php

namespace App\Http\Controllers;

use App\Models\User;

class UserController
{
    public function index()
    {
        // Smell: should use ->count() directly
        return User::where('active', true)->get()->count();
    }

    public function show()
    {
        // Smell: ->all()->count() is also wasteful
        return User::all()->count();
    }

    public function admin()
    {
        // Smell: local query builder variable
        $query = User::where('role', 'admin');
        return $query->get()->count();
    }
}
