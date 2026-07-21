<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Collection;

class DashboardController
{
    public function index()
    {
        // Correct: uses ->count() directly on query
        return User::where('active', true)->count();
    }

    public function show()
    {
        // Correct: Model::count()
        return User::count();
    }

    public function stats()
    {
        // Correct: count on a Collection not from get()
        $ids = collect([1, 2, 3]);
        return $ids->count();
    }

    public function users()
    {
        // Correct: ->get() without ->count()
        return User::where('active', true)->get();
    }
}
